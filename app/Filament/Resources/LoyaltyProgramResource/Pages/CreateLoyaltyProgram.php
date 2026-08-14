<?php

namespace App\Filament\Resources\LoyaltyProgramResource\Pages;

use App\Filament\Resources\LoyaltyProgramResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateLoyaltyProgram extends CreateRecord
{
    protected static string $resource = LoyaltyProgramResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['google_class_suffix'])) {
            $data['google_class_suffix'] = 'loyalty-' . Str::slug($data['name']) . '-' . now()->timestamp;
        }

        // loyalty_programs.reward_title es una columna heredada (NOT NULL, sin default) que
        // espeja el premio del primer sistema de premios. El Repeater de prizeSystems guarda
        // directo en la tabla relacionada y nunca la asigna en el padre, así que sin esto el
        // insert falla en cualquier programa nuevo.
        $firstSystem = $data['prizeSystems'][0] ?? null;
        $data['reward_title']       = $firstSystem['reward_title'] ?? '—';
        $data['reward_description'] = $firstSystem['reward_description'] ?? null;

        return $data;
    }
}
