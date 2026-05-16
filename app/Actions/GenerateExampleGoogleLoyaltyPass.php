<?php

namespace App\Actions;

use Illuminate\Support\Str;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassBuilder;
use Spatie\LaravelMobilePass\Enums\BarcodeType;
use Spatie\LaravelMobilePass\Models\MobilePass;

class GenerateExampleGoogleLoyaltyPass
{
    public function __construct(private EnsureGoogleLoyaltyClass $ensureClass) {}

    public function execute(): MobilePass
    {
        $this->ensureClass->execute('mi-club-membership');

        return LoyaltyPassBuilder::make()
            ->setClass('mi-club-membership')
            ->setAccountId('USER-7842')
            ->setAccountName('David Rodriguez')
            ->setBalanceString('7 / 10')
            ->setBarcode(BarcodeType::Qr, Str::random(24))
            ->save();
    }
}
