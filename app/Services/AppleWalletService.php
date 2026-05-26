<?php

namespace App\Services;

use App\Builders\Apple\LoyaltyStoreCardBuilder;
use App\Models\LoyaltyCard;
use Spatie\LaravelMobilePass\Enums\BarcodeType;
use Spatie\LaravelMobilePass\Models\MobilePass;

class AppleWalletService
{
    public function __construct(private AppleStampImageService $stampImage) {}

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
        $stripPaths   = $this->stampImage->regenerateFor($card);

        /** @var LoyaltyStoreCardBuilder $builder */
        $builder = LoyaltyStoreCardBuilder::make()
            ->setOrganizationName($business->name)
            ->setDescription($program->name . ' — ' . $business->name)
            ->setDownloadName(str($business->name)->slug() . '-loyalty.pkpass')
            ->setBackgroundColor($business->primary_color)
            ->setForegroundColor($business->secondary_color)
            ->setLabelColor($business->label_color)
            ->setIconImage(...$this->iconPaths())
            ->addHeaderField('program', $program->name, label: $business->name)
            ->addField('stamps', $this->stampsField($card), label: 'Sellos', changeMessage: 'Nuevo sello agregado')
            ->addSecondaryField('holder', $card->holder_name, label: 'Miembro')
            ->addSecondaryField('card-id', 'CARD-' . str_pad($card->id, 6, '0', STR_PAD_LEFT), label: 'No. Tarjeta')
            ->addAuxiliaryField('reward', $program->reward_title, label: 'Premio')
            ->setBarcode(BarcodeType::Qr, $barcodeValue);

        // Logo: descarga la URL del negocio y la embebe (igual que Google usa logo_url)
        $logoPath = $this->fetchLogoPath($business->logo_url);
        if ($logoPath) {
            $builder->setLogoImage($logoPath);
        }

        // Strip: imagen dinámica de sellos generada localmente, embebida en el .pkpass
        if (file_exists($stripPaths['x2'])) {
            $builder->setStripImage($stripPaths['x1'], $stripPaths['x2']);
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

        // Regenera la imagen de sellos para el próximo download
        $this->stampImage->regenerateFor($card);

        $pass->updateField('stamps', $this->stampsField($card), changeMessage: 'Nuevo sello agregado');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Descarga la URL del logo del negocio a storage local y retorna el path.
     * Apple Wallet necesita archivos locales; Google Wallet usa la URL directamente.
     * El archivo se cachea por URL hash para no re-descargar en cada llamada.
     */
    private function fetchLogoPath(?string $url): ?string
    {
        if (! $url) {
            return null;
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
            if ($contents === false) {
                return null;
            }
            file_put_contents($path, $contents);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retorna los paths de los iconos base (icon.png requerido por Apple Wallet).
     * Usa los iconos genéricos en storage/app/apple-pass/.
     */
    private function iconPaths(): array
    {
        $dir = storage_path('app/apple-pass');

        return [
            $dir . '/icon.png',
            $dir . '/icon@2x.png',
            $dir . '/icon@3x.png',
        ];
    }

    private function stampsField(LoyaltyCard $card): string
    {
        $program = $card->loyaltyProgram;
        $icon    = $program->stampIconLabel();

        $filled = str_repeat($icon . ' ', min($card->stamps_collected, $program->total_stamps));
        $empty  = str_repeat('○ ', max(0, $program->total_stamps - $card->stamps_collected));

        return trim($filled . $empty);
    }
}
