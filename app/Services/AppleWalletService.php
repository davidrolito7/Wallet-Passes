<?php

namespace App\Services;

use App\Builders\Apple\LoyaltyStoreCardBuilder;
use App\Models\LoyaltyCard;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelMobilePass\Enums\BarcodeType;
use Spatie\LaravelMobilePass\Models\MobilePass;

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
            ->addHeaderField('member_since', 'Desde ' . $card->created_at->year, label: $business->name)
            // Primary: contador de visitas (campo prominente)
            ->addField('progress', $card->stamps_collected . ' / ' . $program->total_stamps, label: 'Visitas')
            // Secondary: nombre y número de tarjeta
            ->addSecondaryField('holder', $card->holder_name, label: 'Miembro')
            ->addSecondaryField('card_id', 'CARD-' . str_pad($card->id, 6, '0', STR_PAD_LEFT), label: 'No. Tarjeta')
            // Auxiliary: próximo premio
            ->addAuxiliaryField('next_reward', $this->nextRewardText($card), label: 'Próximo Premio')
            // Reverso de la tarjeta
            ->addBackField('program_name', $program->name, label: 'Programa')
            ->addBackField('total_stamps', $program->total_stamps . ' visitas para completar', label: 'Meta')
            ->addBackField('reward', $program->reward_title, label: 'Premio Final')
            ->setBarcode(BarcodeType::Qr, $barcodeValue);

        // Logo del negocio (descargado localmente desde la URL/storage del negocio)
        $logoPath = $this->fetchLogoPath($business->logoPublicUrl());
        if ($logoPath) {
            $builder->setLogoImage($logoPath);
        }

        // Imagen de fondo del programa (sube el usuario en Filament)
        $bgPath = $program->backgroundImagePath();
        if ($bgPath && file_exists($bgPath)) {
            $builder->setStripImage($bgPath);
        }

        return $builder->save();
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

        $pass->updateField('progress', $card->stamps_collected . ' / ' . $card->loyaltyProgram->total_stamps, changeMessage: 'Nueva visita registrada');
        $pass->updateField('next_reward', $this->nextRewardText($card));
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
                : $next->reward_title . ' (faltan ' . $remaining . ')';
        }

        $remaining = $program->total_stamps - $card->stamps_collected;

        if ($remaining <= 0) {
            return '¡' . $program->reward_title . ' disponible!';
        }

        return $program->reward_title . ' (faltan ' . $remaining . ')';
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
