<?php

namespace App\Filament\Resources\BusinessResource\Concerns;

use App\Services\GoogleMapsLinkResolver;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

trait ResolvesMapsLink
{
    protected function resolveMapsLink(array $data): array
    {
        $mapsLink = $data['maps_link'] ?? null;
        unset($data['maps_link']);

        if (! ($data['location_enabled'] ?? false)) {
            return $data;
        }

        if (filled($mapsLink)) {
            try {
                [$data['latitude'], $data['longitude']] = app(GoogleMapsLinkResolver::class)->extract($mapsLink);
            } catch (\Throwable $e) {
                Notification::make()
                    ->title('No se pudo leer el enlace de Google Maps')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                throw new Halt();
            }

            return $data;
        }

        if (empty($this->record?->latitude) || empty($this->record?->longitude)) {
            Notification::make()
                ->title('Falta la ubicación')
                ->body('Pega el enlace de Google Maps del negocio para activar la notificación al acercarse.')
                ->danger()
                ->send();

            throw new Halt();
        }

        return $data;
    }
}
