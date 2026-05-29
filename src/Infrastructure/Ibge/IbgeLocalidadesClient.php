<?php

declare(strict_types=1);

namespace App\Infrastructure\Ibge;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class IbgeLocalidadesClient
{
    public function __construct(
        private readonly Client $http,
        private readonly string $baseUrl,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function fetchStates(): array
    {
        return $this->getJson(rtrim($this->baseUrl, '/') . '/estados') ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function fetchMunicipalities(int $stateIbgeId): array
    {
        return $this->getJson(
            rtrim($this->baseUrl, '/') . '/estados/' . $stateIbgeId . '/municipios'
        ) ?? [];
    }

    /** @return list<array<string, mixed>>|null */
    private function getJson(string $url): ?array
    {
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
