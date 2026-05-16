<?php

namespace App\Filament\Resources\LoyaltyCardResource\Pages;

use App\Filament\Resources\LoyaltyCardResource;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Services\LoyaltyService;
use Filament\Resources\Pages\CreateRecord;

class CreateLoyaltyCard extends CreateRecord
{
    protected static string $resource = LoyaltyCardResource::class;

    protected function handleRecordCreation(array $data): LoyaltyCard
    {
        $program = LoyaltyProgram::findOrFail($data['loyalty_program_id']);

        return app(LoyaltyService::class)->createCard(
            program: $program,
            holderName: $data['holder_name'],
            holderEmail: $data['holder_email'] ?? null,
            holderIdentifier: $data['holder_identifier'] ?? null,
        );
    }
}
