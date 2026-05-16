<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Actions\EnsureGoogleLoyaltyClass;

#[Signature('app:create-membership-pass-class')]
#[Description('Command description')]
class CreateMembershipPassClass extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnsureGoogleLoyaltyClass $ensureClass): int
    {
        $ensureClass->execute('mi-club-membership');

        $this->info('Clase creada/actualizada exitosamente en Google Wallet.');

        return self::SUCCESS;
    }
}
