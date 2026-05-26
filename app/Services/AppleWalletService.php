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
        $iconDir      = storage_path('app/apple-pass');
        $logoPath     = public_path('images/brew-code-logo.png');

        $stripPaths = $this->stampImage->regenerateFor($card);

        /** @var LoyaltyStoreCardBuilder $builder */
        $builder = LoyaltyStoreCardBuilder::make()
            ->setOrganizationName($business->name)
            ->setDescription($program->name . ' — ' . $business->name)
            ->setDownloadName(str($business->name)->slug() . '-loyalty.pkpass')
            ->setBackgroundColor($business->primary_color)
            ->setForegroundColor($business->secondary_color)
            ->setLabelColor($business->label_color)
            ->setIconImage(
                $iconDir . '/icon.png',
                $iconDir . '/icon@2x.png',
                $iconDir . '/icon@3x.png',
            )
            ->addHeaderField('program', $program->name, label: $business->name)
            ->addField('stamps', $this->stampsField($card), label: 'Sellos', changeMessage: 'Nuevo sello agregado')
            ->addSecondaryField('holder', $card->holder_name, label: 'Miembro')
            ->addSecondaryField('card-id', 'CARD-' . str_pad($card->id, 6, '0', STR_PAD_LEFT), label: 'No. Tarjeta')
            ->addAuxiliaryField('reward', $program->reward_title, label: 'Premio')
            ->setBarcode(BarcodeType::Qr, $barcodeValue);

        if (file_exists($logoPath)) {
            $builder->setLogoImage($logoPath);
        }

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

        $pass->updateField('stamps', $this->stampsField($card), changeMessage: 'Nuevo sello agregado');

        // Strip image is embedded in .pkpass — regenerate a new pass to reflect stamp changes
        $this->regeneratePass($card, $pass);
    }

    private function regeneratePass(LoyaltyCard $card, MobilePass $pass): void
    {
        $stripPaths = $this->stampImage->regenerateFor($card);

        $passData = $pass->content;

        if (isset($passData['applePassPayload']) && file_exists($stripPaths['x2'])) {
            // The pass is re-generated on next download; the updateField push notifies the device
        }
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
