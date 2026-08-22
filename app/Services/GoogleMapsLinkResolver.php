<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleMapsLinkResolver
{
    /**
     * @return array{0: float, 1: float} [latitude, longitude]
     */
    public function extract(string $url): array
    {
        $coordinates = $this->parse($url);

        if (! $coordinates && $this->isShortLink($url)) {
            $resolved = $this->resolveShortLink($url);
            $coordinates = $resolved ? $this->parse($resolved) : null;
        }

        if (! $coordinates) {
            throw new RuntimeException(
                'No se encontraron coordenadas en ese enlace. Abre el negocio en Google Maps, toca "Compartir" → "Copiar enlace" y pégalo aquí.'
            );
        }

        return $coordinates;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function parse(string $url): ?array
    {
        // Pin exacto del lugar (más preciso que el centro del mapa), presente cuando el
        // enlace apunta a un lugar específico: ...!3d19.432608!4d-99.133209...
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        // Centro del mapa: .../@19.432608,-99.133209,17z/...
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        // ?q=19.432608,-99.133209 o &ll=19.432608,-99.133209
        if (preg_match('/[?&](?:q|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        return null;
    }

    private function isShortLink(string $url): bool
    {
        return (bool) preg_match('/(maps\.app\.goo\.gl|goo\.gl\/maps)/i', $url);
    }

    private function resolveShortLink(string $url): ?string
    {
        try {
            $response = Http::timeout(5)->get($url);

            return (string) $response->effectiveUri() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
