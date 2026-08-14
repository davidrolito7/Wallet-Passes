<?php

namespace App\Filament\Resources\LoyaltyProgramResource\Pages;

use App\Filament\Resources\LoyaltyProgramResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyProgram extends EditRecord
{
    protected static string $resource = LoyaltyProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Mantiene sincronizado el espejo heredado loyalty_programs.reward_title con el
        // primer sistema de premios (ver CreateLoyaltyProgram::mutateFormDataBeforeCreate).
        $firstSystem = $data['prizeSystems'][0] ?? null;
        $data['reward_title']       = $firstSystem['reward_title'] ?? '—';
        $data['reward_description'] = $firstSystem['reward_description'] ?? null;

        return $data;
    }
}
