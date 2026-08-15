<?php

namespace App\Services;

use App\Builders\Apple\LoyaltyStoreCardBuilder;
use App\Models\Business;
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

        // Header: fecha de vigencia (mes/año) si el programa la tiene configurada; si no,
        // se conserva el comportamiento anterior de mostrar la fecha de emisión.
        $validUntil  = $card->validUntil();
        $headerValue = $validUntil
            ? 'Vigencia ' . $validUntil->format('m/y')
            : 'Desde ' . $card->created_at->format('m/y');

        /** @var LoyaltyStoreCardBuilder $builder */
        $builder = LoyaltyStoreCardBuilder::make()
            ->setOrganizationName($business->name)
            ->setDescription($program->name . ' — ' . $business->name)
            ->setDownloadName(str($business->name)->slug() . '-loyalty.pkpass')
            ->setBackgroundColor($business->primary_color)
            ->setForegroundColor($business->secondary_color)
            ->setLabelColor($business->label_color)
            ->setIconImage(...$this->iconPathsForBusiness($card))
            // Header: nombre del negocio / vigencia de la tarjeta
            ->addHeaderField('validity', $headerValue, label: $business->name)
            // Secondary: miembro + contador de visitas solo cuando hay stickers
            ->addSecondaryField('holder', $card->fullName(), label: 'Miembro')
            // Auxiliary: próximo premio
            ->addAuxiliaryField('next_reward', $this->nextRewardText($card), label: 'Próximo Premio')
            // Reverso de la tarjeta
            ->addBackField('program_name', filled($program->description) ? $program->description : $program->name, label: 'Términos')
            ->addBackField('member_since', $card->created_at->translatedFormat('F Y'), label: 'Miembro desde')
            ->addBackField('prizes_list', $this->prizesListText($card), label: 'Premios')
            ->withBackFieldRight('prizes_list')
            ->addBackField('contact', $this->contactText($card), label: 'Contacto')
            // wallet_msg: campo portador de notificaciones. Su valor es la última notificación
            // enviada. changeMessage '%@' hace que Apple muestre el valor como texto del push.
            ->addBackField('wallet_msg', '', label: 'Aviso')
            ->addBackField('dev_footer', 'Alebrije Tech', label: 'Desarrollado por')
            ->withBackFieldAttributedValue('dev_footer', "<a href='https://alebrijetech.com'>Alebrije Tech</a>")
            ->setBarcode(BarcodeType::Qr, $barcodeValue);

        $contactAttr = $this->contactAttributedValue($card);
        if ($contactAttr) {
            $builder->withBackFieldAttributedValue('contact', $contactAttr);
        }

        if ($hasStickers) {
            $builder->addSecondaryField('card_id', 'Vista ' . $card->stamps_collected . '/' . $program->total_stamps, label: 'Visitas');
        } else {
            // Sin stickers: contador prominente como campo primario
            $builder->addField('progress', $card->stamps_collected . ' / ' . $program->total_stamps, label: 'Visitas');
        }

        // Logo del negocio
        $logoPaths = $this->logoPathsForBusiness($business);
        if ($logoPaths) {
            $builder->setLogoImage(...$logoPaths);
        }

        // Ubicación: la tarjeta aparece sola en la pantalla de bloqueo al acercarse al negocio.
        // Se fija solo al crear el pase — el builder no ofrece forma de reemplazar ubicaciones
        // ya guardadas en un pase existente (addLocation() siempre agrega, nunca reemplaza), así
        // que si el negocio cambia su dirección después, los pases ya instalados no se enteran.
        if ($business->hasLocation()) {
            $builder->addLocation(
                latitude: (float) $business->latitude,
                longitude: (float) $business->longitude,
                relevantText: $business->location_relevant_text,
            );
            $builder->setMaxDistance($business->location_radius_meters ?? 150);
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

        // Actualizar next_reward y la lista de premios con el estado actual.
        $builder->updateField('next_reward', $this->nextRewardText($card));
        $builder->updateField('prizes_list', $this->prizesListText($card));

        // wallet_msg: portador de la notificación push.
        // Apple PassFieldContent docs: %@ es OBLIGATORIO en changeMessage para que Apple muestre
        // el texto personalizado; sin él muestra el genérico localizado ("La tarjeta de tienda cambió").
        // El value debe cambiar entre versiones para disparar la alerta, incluso cuando el texto
        // configurado es estático. Se agrega un marcador invisible (espacios de ancho cero) en vez
        // de un contador visible, para no duplicar el "X/Y sellos" que ya se ve en la tarjeta.
        $notifText  = $this->resolveVisitMessage($card);
        $fieldValue = $notifText . str_repeat("\u{200B}", $card->stamps_collected);
        $builder->addBackField('wallet_msg', $fieldValue, label: 'Aviso', changeMessage: '%@');

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
        // %@ es obligatorio en changeMessage (Apple lo exige para mostrar texto personalizado).
        // El value debe cambiar entre envíos para disparar la alerta, incluso si el mensaje es
        // idéntico al anterior. Se agrega un marcador invisible (espacios de ancho cero) en vez
        // de una fecha visible, igual que en updatePass().
        $fieldValue = $message . str_repeat("\u{200B}", random_int(1, 30));

        $pass->builder()
            ->addBackField('wallet_msg', $fieldValue, label: 'Aviso', changeMessage: '%@')
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

    private function prizesListText(LoyaltyCard $card): string
    {
        $program     = $card->loyaltyProgram;
        $prizeSystem = $card->resolvedPrizeSystem();
        $lines       = [];

        foreach ($prizeSystem?->milestones ?? collect() as $m) {
            $lines[] = 'Visita ' . $m->stamp_count . ': ' . $m->reward_title;
        }

        $lines[] = 'Visita ' . $program->total_stamps . ': ' . ($prizeSystem?->reward_title ?? '—') . ' ★';

        return implode("\n", $lines);
    }

    private function contactText(LoyaltyCard $card): string
    {
        $business = $card->loyaltyProgram->business;
        $parts    = array_filter([
            $business->contact_phone,
            filled($business->instagram_url) ? 'Síguenos en Instagram' : null,
        ]);

        return implode("\n", $parts);
    }

    private function contactAttributedValue(LoyaltyCard $card): ?string
    {
        $business = $card->loyaltyProgram->business;

        if (! filled($business->instagram_url)) {
            return null;
        }

        $parts = array_filter([
            $business->contact_phone,
            "<a href='" . e($business->instagram_url) . "'>Síguenos en Instagram</a>",
        ]);

        return implode("\n", $parts);
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
     * Genera el logo del negocio en @1x/@2x/@3x (PNG transparente, sin recorte forzado)
     * para el frente del pase. setLogoImage() acepta las tres resoluciones, pero si solo se
     * le da una (como antes), Apple Wallet la trata como @1x y la estira en pantallas Retina
     * (2x/3x) — de ahí el borroneo. Nunca se agranda más allá de la resolución de origen.
     * Se cachea por hash del logo para no regenerar en cada llamada.
     */
    private function logoPathsForBusiness(Business $business): array
    {
        $sourcePath = $this->fetchLogoPath($business->logoPublicUrl());

        if (! $sourcePath || ! file_exists($sourcePath)) {
            return [];
        }

        $dir = storage_path('app/apple-pass/logos-hires');
        is_dir($dir) || mkdir($dir, 0755, true);

        $mtime    = (string) (@filemtime($sourcePath) ?: 0);
        $cacheKey = md5($sourcePath . '@' . $mtime);

        $paths = [
            1 => $dir . '/logo_' . $cacheKey . '.png',
            2 => $dir . '/logo_' . $cacheKey . '@2x.png',
            3 => $dir . '/logo_' . $cacheKey . '@3x.png',
        ];

        if (file_exists($paths[1])) {
            return array_values($paths);
        }

        $raw = @file_get_contents($sourcePath);
        $src = $raw !== false ? @imagecreatefromstring($raw) : false;

        if (! $src) {
            return [];
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // Máximo recomendado por Apple: ~160×50 pt @1x. Se respeta ese límite en cada
        // densidad, pero el ratio nunca supera 1.0 para no agrandar más allá del original.
        foreach ([1 => 160, 2 => 320, 3 => 480] as $scale => $maxW) {
            $maxH  = 50 * $scale;
            $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0);
            $w     = max(1, (int) round($srcW * $ratio));
            $h     = max(1, (int) round($srcH * $ratio));

            $canvas = imagecreatetruecolor($w, $h);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagecopyresampled($canvas, $src, 0, 0, 0, 0, $w, $h, $srcW, $srcH);

            imagepng($canvas, $paths[$scale], 9);
            imagedestroy($canvas);
        }

        imagedestroy($src);

        return array_values($paths);
    }

    /**
     * Genera iconos per-negocio (29/58/87 px) con el logo centrado sobre el color primario.
     * Se cachean por hash del logo + color para evitar regenerar en cada llamada.
     * Cae de vuelta al icono negro genérico si no hay logo disponible.
     */
    private function iconPathsForBusiness(LoyaltyCard $card): array
    {
        $business = $card->loyaltyProgram->business;
        $logoPath = $this->fetchLogoPath($business->logoPublicUrl());

        if (! $logoPath || ! file_exists($logoPath)) {
            return $this->iconPaths();
        }

        $dir = storage_path('app/apple-pass/icons');
        is_dir($dir) || mkdir($dir, 0755, true);

        $mtime    = (string) (@filemtime($logoPath) ?: 0);
        $cacheKey = md5($logoPath . '@' . $mtime . '@' . ($business->primary_color ?? ''));

        $icons = [
            29 => $dir . '/icon_' . $cacheKey . '.png',
            58 => $dir . '/icon_' . $cacheKey . '@2x.png',
            87 => $dir . '/icon_' . $cacheKey . '@3x.png',
        ];

        if (! file_exists($icons[29])) {
            $raw = @file_get_contents($logoPath);
            $src = $raw !== false ? @imagecreatefromstring($raw) : false;

            if (! $src) {
                return $this->iconPaths();
            }

            [$r, $g, $b] = $this->hexToRgb($business->primary_color ?? '#1e1e1e');

            $srcW = imagesx($src);
            $srcH = imagesy($src);

            foreach ($icons as $size => $path) {
                $canvas = imagecreatetruecolor($size, $size);
                $bg     = imagecolorallocate($canvas, $r, $g, $b);
                imagefilledrectangle($canvas, 0, 0, $size, $size, $bg);

                // Logo centrado con 10% de padding, manteniendo proporción
                $pad     = (int) round($size * 0.10);
                $maxDim  = $size - $pad * 2;
                $ratio   = min($maxDim / $srcW, $maxDim / $srcH);
                $newW    = (int) round($srcW * $ratio);
                $newH    = (int) round($srcH * $ratio);
                $offsetX = (int) round(($size - $newW) / 2);
                $offsetY = (int) round(($size - $newH) / 2);

                imagealphablending($canvas, true);
                imagecopyresampled($canvas, $src, $offsetX, $offsetY, 0, 0, $newW, $newH, $srcW, $srcH);
                imagepng($canvas, $path);
                imagedestroy($canvas);
            }

            imagedestroy($src);
        }

        return array_values($icons);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
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
