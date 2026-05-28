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
            ->addSecondaryField('holder', $card->holder_name, label: 'Miembro')
            // Auxiliary: próximo premio
            ->addAuxiliaryField('next_reward', $this->nextRewardText($card), label: 'Próximo Premio')
            // Reverso de la tarjeta
            ->addBackField('program_name', $program->name, label: 'Programa')
            ->addBackField('total_stamps', $program->total_stamps . ' visitas para completar', label: 'Meta')
            ->addBackField('reward', $program->reward_title, label: 'Premio Final')
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

        if ($hasStickers) {
            // Regenerar strip con el nuevo conteo de sellos
            $paths  = app(AppleStampImageService::class)->regenerateFor($card);
            $images = $pass->images ?? [];
            $images['strip'] = ['x1Path' => $paths['x1'], 'x2Path' => $paths['x2'], 'x3Path' => null];
            $pass->update(['images' => $images]);

            // Actualizar contador visible en campo card_id
            $pass->updateField('card_id', 'Vista ' . $card->stamps_collected . '/' . $program->total_stamps);

            // Si el pass fue creado sin stickers tendrá campo progress — limpiarlo para no mostrar dato viejo
            if ($this->passHasField($pass, 'progress')) {
                $pass->updateField('progress', '');
            }
        } else {
            // Actualizar campo progress si existe en el pass
            if ($this->passHasField($pass, 'progress')) {
                $pass->updateField('progress', $card->stamps_collected . ' / ' . $program->total_stamps);
            }
        }

        // Push siempre via next_reward — presente en todos los passes independientemente del modo
        $pass->updateField('next_reward', $this->nextRewardText($card), changeMessage: 'Nueva visita registrada');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function nextRewardText(LoyaltyCard $card): string
    {
        $program = $card->loyaltyProgram;

        $next = $program->milestones()
            ->where('stamp_count', '>', $card->stamps_collected)
            ->orderBy('stamp_count')
            ->first();

        if ($next) {
            $remaining = $next->stamp_count - $card->stamps_collected;
            return $remaining <= 0
                ? '¡' . $next->reward_title . ' disponible!'
                : $next->reward_title . ' (en ' . $remaining . ' visitas)';
        }

        $remaining = $program->total_stamps - $card->stamps_collected;

        if ($remaining <= 0) {
            return '¡' . $program->reward_title . ' disponible!';
        }

        return $program->reward_title . ' (en ' . $remaining . ' visitas)';
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
