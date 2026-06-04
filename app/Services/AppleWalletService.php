<?php

namespace App\Services;

use App\Builders\Apple\LoyaltyStoreCardBuilder;
use App\Models\LoyaltyCard;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelMobilePass\Enums\BarcodeType;
use Spatie\LaravelMobilePass\Models\MobilePass;
use App\Services\AppleStampImageService;

class AppleWalletService
{
    public function isConfigured(): bool
    {
        return filled(config('mobile-pass.apple.type_identifier'))
            && filled(config('mobile-pass.apple.team_identifier'))
            && (filled(config('mobile-pass.apple.certificate')) || filled(config('mobile-pass.apple.certificate_path')));
    }

    public function createPass(LoyaltyCard $card): ?MobilePass
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $program  = $card->loyaltyProgram;
        $business = $program->business;

        $barcodeValue = 'loyalty:' . $card->id . ':' . md5($card->id . $card->created_at);

        $hasStickers = (bool) ($program->filled_stamp_image || $program->empty_stamp_image);

        /** @var LoyaltyStoreCardBuilder $builder */
        $builder = LoyaltyStoreCardBuilder::make()
            ->setOrganizationName($business->name)
            ->setDescription($program->name . ' — ' . $business->name)
            ->setDownloadName(str($business->name)->slug() . '-loyalty.pkpass')
            ->setBackgroundColor($business->primary_color)
            ->setForegroundColor($business->secondary_color)
            ->setLabelColor($business->label_color)
            ->setIconImage(...$this->iconPaths())
            // Header: nombre del programa / año de membresía
            ->addHeaderField('member_since', 'Desde ' . $card->created_at->format('m/y'), label: $business->name)
            // Secondary: miembro + contador de visitas solo cuando hay stickers
            ->addSecondaryField('holder', $card->fullName(), label: 'Miembro')
            // Auxiliary: próximo premio
            ->addAuxiliaryField('next_reward', $this->nextRewardText($card), label: 'Próximo Premio')
            // Reverso de la tarjeta
            ->addBackField('program_name', $program->name, label: 'Programa')
            ->addBackField('total_stamps', $program->total_stamps . ' visitas para completar', label: 'Meta')
            ->addBackField('reward', $card->resolvedPrizeSystem()?->reward_title ?? '—', label: 'Premio Final')
            // wallet_msg: campo portador de notificaciones. Su valor es la última notificación
            // enviada. changeMessage '%@' hace que Apple muestre el valor como texto del push.
            ->addBackField('wallet_msg', '', label: 'Aviso')
            ->setBarcode(BarcodeType::Qr, $barcodeValue);

        if ($hasStickers) {
            $builder->addSecondaryField('card_id', 'Vista ' . $card->stamps_collected . '/' . $program->total_stamps, label: 'Visitas');
        } else {
            // Sin stickers: contador prominente como campo primario
            $builder->addField('progress', $card->stamps_collected . ' / ' . $program->total_stamps, label: 'Visitas');
        }

        // Logo del negocio
        $logoPath = $this->fetchLogoPath($business->logoPublicUrl());
        if ($logoPath) {
            $builder->setLogoImage($logoPath);
        }

        // Versión con stickers: genera strip dinámico con cuadrícula de sellos 3×N
        // Versión de texto: usa la imagen de fondo estática (fallback "1/10")
        if ($program->filled_stamp_image || $program->empty_stamp_image) {
            $paths = app(AppleStampImageService::class)->pathsFor($card);
            $builder->setStripImage($paths['x1'], $paths['x2']);
        } else {
            $bgPath = $program->backgroundImagePath();
            if ($bgPath && file_exists($bgPath)) {
                $builder->setStripImage($bgPath);
            }
        }

        $pass = $builder->save();

        // Spatie's RegisterDeviceAction does findOrFail($passSerial) by primary key,
        // so serialNumber in the pass JSON must equal the MobilePass id.
        $content = $pass->content;
        if (($content['serialNumber'] ?? null) !== $pass->id) {
            $content['serialNumber'] = $pass->id;
            MobilePass::withoutEvents(fn () => $pass->update(['content' => $content]));
            $pass->content = $content;
        }

        return $pass;
    }

    public function updatePass(LoyaltyCard $card): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $pass = $card->applePass();

        if (! $pass) {
            return;
        }

        $program     = $card->loyaltyProgram;
        $hasStickers = (bool) ($program->filled_stamp_image || $program->empty_stamp_image);

        // Actualizar imágenes sin disparar APNS (el push lo gestiona el builder abajo).
        if ($hasStickers) {
            $paths  = app(AppleStampImageService::class)->regenerateFor($card);
            $images = $pass->images ?? [];
            $images['strip'] = ['x1Path' => $paths['x1'], 'x2Path' => $paths['x2'], 'x3Path' => null];
            MobilePass::withoutEvents(fn () => $pass->update(['images' => $images]));
            // Refrescar el modelo para que el builder cargue las imágenes nuevas.
            $pass->images = $images;
        }

        // Usar el builder para actualizar todos los campos en un único save() → un único push APNS.
        $builder = $pass->builder();

        if ($hasStickers) {
            $builder->updateField('card_id', 'Vista ' . $card->stamps_collected . '/' . $program->total_stamps);
            if ($this->passHasField($pass, 'progress')) {
                $builder->updateField('progress', '');
            }
        } else {
            if ($this->passHasField($pass, 'progress')) {
                $builder->updateField('progress', $card->stamps_collected . ' / ' . $program->total_stamps);
            }
        }

        // Actualizar next_reward con el premio real (SIN changeMessage — no genera notificación).
        $builder->updateField('next_reward', $this->nextRewardText($card));

        // wallet_msg: portador de la notificación push.
        // Apple requiere '%@' en changeMessage. Al poner '%@' el texto de la notificación
        // es el VALOR del campo (wallet_msg). Así el mensaje no toca next_reward.
        $notifText = $this->resolveVisitMessage($card);
        $builder->addBackField('wallet_msg', $notifText, label: 'Aviso', changeMessage: '%@');

        // Un solo save → un solo push APNS con el mensaje correcto.
        $builder->save();
    }

    /**
     * Envía un mensaje manual al Apple Wallet de una tarjeta.
     *
     * Apple no tiene un endpoint addmessage como Google. La notificación push se activa
     * actualizando un campo del pase con changeMessage: '%@'. El valor del campo ES el texto
     * que aparece en la notificación de iPhone. Se usa el campo back 'wallet_msg' para no
     * interferir con los campos visibles del frente del pase.
     *
     * Si $notify es false se omite la actualización completa (no hay forma de actualizar
     * el pase en Apple sin que el dispositivo registrado reciba la notificación de cambio).
     */
    public function sendMessage(LoyaltyCard $card, string $message, bool $notify = true): void
    {
        if (! $this->isConfigured() || ! $notify) {
            return;
        }

        $pass = $card->applePass();

        if (! $pass) {
            return;
        }

        // addBackField agrega wallet_msg si no existe (pases anteriores) o lo actualiza si ya existe.
        // changeMessage: '%@' → la notificación muestra el valor del campo = $message.
        $pass->builder()
            ->addBackField('wallet_msg', $message, label: 'Aviso', changeMessage: '%@')
            ->save();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Devuelve el texto que aparecerá en la notificación push de Apple al registrar una visita.
     * Usa el mensaje configurado en el programa; si no hay uno, devuelve el texto por defecto.
     */
    private function resolveVisitMessage(LoyaltyCard $card): string
    {
        $program = $card->loyaltyProgram;

        if (
            ($program->visit_notification_enabled ?? false)
            && filled($program->visit_notification_message)
        ) {
            return $this->resolveTemplate($program->visit_notification_message, $card);
        }

        return 'Nueva visita registrada';
    }

    /**
     * Sustituye variables de plantilla con valores reales de la tarjeta y el programa.
     * Variables soportadas: {first_name} {full_name} {stamps_collected} {total_stamps}
     *                       {remaining_stamps} {business_name} {program_name}
     *                       {next_reward} {reward_title}
     */
    private function resolveTemplate(string $template, LoyaltyCard $card): string
    {
        $program     = $card->loyaltyProgram;
        $business    = $program->business;
        $prizeSystem = $card->resolvedPrizeSystem();

        $next = $prizeSystem?->milestones()
            ->where('stamp_count', '>', $card->stamps_collected)
            ->first();

        $remaining   = max(0, ($next?->stamp_count ?? $program->total_stamps) - $card->stamps_collected);
        $rewardTitle = $prizeSystem?->reward_title ?? '—';

        $vars = [
            '{first_name}'       => $card->first_name,
            '{full_name}'        => $card->fullName(),
            '{stamps_collected}' => $card->stamps_collected,
            '{total_stamps}'     => $program->total_stamps,
            '{remaining_stamps}' => $remaining,
            '{business_name}'    => $business->name,
            '{program_name}'     => $program->name,
            '{next_reward}'      => $next?->reward_title ?? $rewardTitle,
            '{reward_title}'     => $rewardTitle,
        ];

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    private function nextRewardText(LoyaltyCard $card): string
    {
        $program     = $card->loyaltyProgram;
        $prizeSystem = $card->resolvedPrizeSystem();

        $next = $prizeSystem?->milestones()
            ->where('stamp_count', '>', $card->stamps_collected)
            ->first();

        if ($next) {
            $remaining = $next->stamp_count - $card->stamps_collected;
            return $remaining <= 0
                ? '¡' . $next->reward_title . ' disponible!'
                : $next->reward_title . ' (en ' . $remaining . ' visitas)';
        }

        $rewardTitle = $prizeSystem?->reward_title ?? '—';
        $remaining   = $program->total_stamps - $card->stamps_collected;

        if ($remaining <= 0) {
            return '¡' . $rewardTitle . ' disponible!';
        }

        return $rewardTitle . ' (en ' . $remaining . ' visitas)';
    }

    private function passHasField(MobilePass $pass, string $key): bool
    {
        $content = $pass->content;

        foreach (['storeCard', 'boardingPass', 'coupon', 'eventTicket', 'generic'] as $passType) {
            if (! isset($content[$passType])) {
                continue;
            }
            foreach (['primaryFields', 'secondaryFields', 'auxiliaryFields', 'headerFields', 'backFields'] as $group) {
                foreach ($content[$passType][$group] ?? [] as $field) {
                    if (($field['key'] ?? null) === $key) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Descarga la URL del logo del negocio a storage local y retorna el path.
     * Cachea por hash de URL para no re-descargar en cada llamada.
     * Si logo_url es un path de storage (FileUpload), lo resuelve directamente.
     */
    private function fetchLogoPath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        // Si es un path relativo de storage (subido con FileUpload)
        if (! str_starts_with($url, 'http')) {
            $path = Storage::disk('public')->path($url);
            return file_exists($path) ? $path : null;
        }

        $cacheDir = storage_path('app/apple-pass/logos');
        is_dir($cacheDir) || mkdir($cacheDir, 0755, true);

        $ext  = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
        $path = $cacheDir . '/' . md5($url) . '.' . $ext;

        if (file_exists($path)) {
            return $path;
        }

        try {
            $contents = @file_get_contents($url);
            if ($contents !== false) {
                file_put_contents($path, $contents);
                return $path;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Genera los iconos base (29/58/87 px) si no existen.
     * Requeridos por Apple Wallet — se auto-crean en cualquier servidor.
     */
    private function iconPaths(): array
    {
        $dir = storage_path('app/apple-pass');
        is_dir($dir) || mkdir($dir, 0755, true);

        foreach ([29 => 'icon.png', 58 => 'icon@2x.png', 87 => 'icon@3x.png'] as $size => $filename) {
            $path = $dir . '/' . $filename;
            if (! file_exists($path)) {
                $img = imagecreatetruecolor($size, $size);
                imagefilledrectangle($img, 0, 0, $size, $size, imagecolorallocate($img, 30, 30, 30));
                imagepng($img, $path);
                imagedestroy($img);
            }
        }

        return [
            $dir . '/icon.png',
            $dir . '/icon@2x.png',
            $dir . '/icon@3x.png',
        ];
    }
}
