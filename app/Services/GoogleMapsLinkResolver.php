<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleMapsLinkResolver
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * @return array{0: float, 1: float} [latitude, longitude]
     */
    public function extract(string $url): array
    {
        $coordinates = $this->parse($url);

        // Un link corto (maps.app.goo.gl, g.co/kgs) casi siempre resuelve a una URL larga con
        // las coordenadas reales del pin — se busca ahí. IMPORTANTE: no se debe intentar sacar
        // coordenadas del HTML/JS de la página en sí. Cuando el link comparte la FICHA de un
        // negocio (no un pin suelto) Google resuelve a algo como
        // "...maps?q=Nombre+del+lugar,+Calle+123&ftid=0x85c7..." sin coordenadas reales en
        // ningún lado, y la página solo trae un mapa genérico de vista previa (og:image) que
        // puede estar centrado a cientos de km del negocio real — devolver eso sería peor que
        // no encontrar nada, porque ubicaría mal el negocio sin que nadie lo note.
        if (! $coordinates) {
            $response = $this->safeGet($url);

            if ($response) {
                $coordinates = $this->parse((string) $response->effectiveUri());
            }
        }

        if (! $coordinates) {
            throw new RuntimeException(
                'No se encontraron coordenadas en ese enlace. Esto pasa cuando compartes la ficha del negocio '
                .'directamente (Google no expone sus coordenadas exactas por ese medio). En vez de eso: abre Google '
                .'Maps, mantén presionado el pin exacto del negocio para soltar una ubicación en ese punto, y comparte '
                .'ESE enlace ("Compartir" → "Copiar enlace").'
            );
        }

        return $coordinates;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function parse(string $content): ?array
    {
        // Pin exacto del lugar (más preciso que el centro del mapa), presente cuando el
        // enlace apunta a un lugar específico: ...!3d19.432608!4d-99.133209...
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $content, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        // Centro del mapa: .../@19.432608,-99.133209,17z/...
        if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $content, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        // ?q=19.432608,-99.133209 · &ll=19.432608,-99.133209 (la coma a veces viene URL-encoded
        // como %2C en la URL final después de un redirect, así que se acepta cualquiera de las
        // dos formas).
        if (preg_match('/[?&](?:q|ll|center)=(-?\d+\.\d+)(?:,|%2C)(-?\d+\.\d+)/i', $content, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        return null;
    }

    private function safeGet(string $url): ?Response
    {
        try {
            return $this->httpRequest()->get($url);
        } catch (\Throwable) {
            return null;
        }
    }

    private function httpRequest(): PendingRequest
    {
        // Sin un User-Agent de navegador real, Google a veces responde con una página de
        // consentimiento o un cuerpo recortado que no trae las coordenadas.
        return Http::timeout(6)->withUserAgent(self::USER_AGENT);
    }
}
