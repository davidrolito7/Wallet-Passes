<?php

namespace App\Filament\Resources\BusinessResource\Pages;

use App\Filament\Resources\BusinessResource;
use App\Filament\Resources\BusinessResource\Concerns\ResolvesMapsLink;
use Filament\Resources\Pages\CreateRecord;

class CreateBusiness extends CreateRecord
{
    use ResolvesMapsLink;

    protected static string $resource = BusinessResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->resolveMapsLink($data);
    }
}
