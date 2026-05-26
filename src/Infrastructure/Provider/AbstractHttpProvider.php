<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

abstract class AbstractHttpProvider
{
    public function __construct(protected readonly Client $http)
    {
    }

    protected function getJson(string $url): ?array
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

    protected function isCepError(array $data): bool
    {
        if (isset($data['erro']) && $data['erro'] === true) {
            return true;
        }
        if (isset($data['ok']) && $data['ok'] === false) {
            return true;
        }
        if (isset($data['status']) && $data['status'] !== 200 && isset($data['ok']) && $data['ok'] === false) {
            return true;
        }

        return false;
    }
}
