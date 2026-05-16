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

        return $data;
    }
}
