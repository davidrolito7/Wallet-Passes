<?php

namespace App\Services;

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

        // El link de un NEGOCIO (ficha reclamada / Knowledge Panel) suele compartirse como
        // g.co/kgs/... en vez de maps.app.goo.gl — antes solo se reconocía este último, así que
        // esos enlaces nunca llegaban a resolverse y siempre tiraban "no se encontraron
        // coordenadas", aunque el link fuera válido.
        if (! $coordinates && $this->isShortLink($url)) {
            $resolved = $this->resolveShortLink($url);

            if ($resolved) {
                $coordinates = $this->parse($resolved);
                $url         = $resolved;
            }
        }

        // Último recurso: algunos links de negocio (ej. ?cid=1234567890) no traen coordenadas
        // en la URL en absoluto — solo un ID opaco de Google. En ese caso se descarga la página
        // y se buscan las coordenadas en el HTML (Google siempre las incrusta ahí, aunque no
        // estén en la URL: mapa estático de og:image, canonical, etc.).
        if (! $coordinates) {
            $coordinates = $this->parseFromPageContent($url);
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

        // ?q=19.432608,-99.133209 · &ll=19.432608,-99.133209 · staticmap?center=19.4,-99.1
        // (esta última aparece en el <meta property="og:image"> de la página de un lugar).
        if (preg_match('/[?&](?:q|ll|center)=(-?\d+\.\d+),(-?\d+\.\d+)/', $content, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        return null;
    }

    private function isShortLink(string $url): bool
    {
        return (bool) preg_match('/(maps\.app\.goo\.gl|goo\.gl\/maps|g\.co\/)/i', $url);
    }

    private function resolveShortLink(string $url): ?string
    {
        try {
            $response = $this->httpRequest()->get($url);

            return (string) $response->effectiveUri() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseFromPageContent(string $url): ?array
    {
        try {
            $response = $this->httpRequest()->get($url);

            return $response->successful() ? $this->parse($response->body()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function httpRequest(): \Illuminate\Http\Client\PendingRequest
    {
        // Sin un User-Agent de navegador real, Google a veces responde con una página de
        // consentimiento o un cuerpo recortado que no trae las coordenadas.
        return Http::timeout(6)->withUserAgent(self::USER_AGENT);
    }
}
