<?php

namespace App\Actions;

use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassClass;

class EnsureGoogleLoyaltyClass
{
    public function execute(string $suffix = 'mi-club-membership'): void
    {
        // GoogleWalletClient::insertClass() is upsert-like (POST then PATCH on 409),
        // so calling save() repeatedly is safe.
        LoyaltyPassClass::make($suffix)
            ->setIssuerName('Tarje de lealtad')
            ->setProgramName('Ayook Cafe')
            ->setProgramLogoUrl('https://freight.cargo.site/t/original/i/a270fbbc9ca637f161d317f0c6615755a16e53de74df5e60e000433a8737528a/lepiz_151023_6913.jpg')
            ->setRewardsTier('Gold')
            ->setRewardsTierLabel('Nivel')
            ->setAccountNameLabel('Miembro')
            ->setAccountIdLabel('No. de Socio')
            ->setBackgroundColor('#1a1a2e')
            ->save();
    }
}
