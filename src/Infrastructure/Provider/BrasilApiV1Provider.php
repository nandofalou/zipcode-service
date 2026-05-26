<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Dto\NormalizedAddress;
use App\Domain\Provider\ZipcodeProviderInterface;
use App\Support\Normalizer;

final class BrasilApiV1Provider extends AbstractHttpProvider implements ZipcodeProviderInterface
{
    public function getSlug(): string
    {
        return 'brasilapi-v1';
    }

    public function fetch(string $digitsOnly): ?NormalizedAddress
    {
        $data = $this->getJson('https://brasilapi.com.br/api/cep/v1/' . $digitsOnly);
        if ($data === null) {
            return null;
        }

        $zipcode = Normalizer::zipcode((string) ($data['cep'] ?? $digitsOnly));
        if (strlen($zipcode) !== 8) {
            return null;
        }

        $state = Normalizer::stateAbbr((string) ($data['state'] ?? ''));
        $city = Normalizer::text((string) ($data['city'] ?? ''));
        if ($state === '' || $city === null) {
            return null;
        }

        return new NormalizedAddress(
            zipcode: $zipcode,
            street: Normalizer::text((string) ($data['street'] ?? '')),
            neighborhood: Normalizer::text((string) ($data['neighborhood'] ?? '')),
            stateAbbr: $state,
            cityName: $city,
            ibgeCode: null,
            lat: null,
            lng: null,
            providerSlug: $this->getSlug(),
        );
    }
}
