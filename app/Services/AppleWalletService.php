<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use Spatie\LaravelMobilePass\Builders\Apple\StoreCardPassBuilder;
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

        $program = $card->loyaltyProgram;
        $business = $program->business;

        $barcodeValue = 'loyalty:' . $card->id . ':' . md5($card->id . $card->created_at);

        $builder = StoreCardPassBuilder::make()
            ->setOrganizationName($business->name)
            ->setDescription($program->name . ' - ' . $business->name)
            ->setDownloadName(str($business->name)->slug() . '-loyalty.pkpass')
            ->setBackgroundColor($business->primary_color)
            ->setForegroundColor($business->secondary_color)
            ->setLabelColor($business->label_color)
            ->addHeaderField('program', $program->name, label: $business->name)
            ->addField('stamps', $this->stampsField($card), label: 'Sellos', changeMessage: 'Nuevo sello agregado')
            ->addSecondaryField('holder', $card->holder_name, label: 'Miembro')
            ->addSecondaryField('card-id', 'CARD-' . str_pad($card->id, 6, '0', STR_PAD_LEFT), label: 'No. Tarjeta')
            ->addAuxiliaryField('reward', $program->reward_title, label: 'Premio')
            ->setBarcode(BarcodeType::Qr, $barcodeValue);

        if ($business->logo_url) {
            // When logo is a URL, Apple Wallet requires local files — skip if not downloaded
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

        $content = $pass->content;
        $content['fields']['stamps']['value'] = $this->stampsField($card);

        $pass->update(['content' => $content]);
        $pass->pushUpdateToDevice();
    }

    private function stampsField(LoyaltyCard $card): string
    {
        $program = $card->loyaltyProgram;
        $icon = $program->stampIconLabel();

        $filled = str_repeat($icon . ' ', min($card->stamps_collected, $program->total_stamps));
        $empty = str_repeat('○ ', max(0, $program->total_stamps - $card->stamps_collected));

        return trim($filled . $empty);
    }
}
