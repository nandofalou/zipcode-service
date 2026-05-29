<?php

declare(strict_types=1);

namespace App\Infrastructure\Geocode;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class NominatimClient
{
    public function __construct(
        private readonly Client $http,
        private readonly string $baseUrl,
    ) {
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/reverse?' . http_build_query([
            'format' => 'jsonv2',
            'lat' => $lat,
            'lon' => $lng,
        ]);

        try {
            $response = $this->http->get($url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (GuzzleException|\JsonException) {
            return null;
        }
    }
}
