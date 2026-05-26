<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Dto\NormalizedAddress;
use App\Domain\Provider\ZipcodeProviderInterface;
use App\Support\Normalizer;

final class AwesomeApiProvider extends AbstractHttpProvider implements ZipcodeProviderInterface
{
    public function getSlug(): string
    {
        return 'awesomeapi';
    }

    public function fetch(string $digitsOnly): ?NormalizedAddress
    {
        $data = $this->getJson('https://cep.awesomeapi.com.br/json/' . $digitsOnly);
        if ($data === null || $this->isCepError($data)) {
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
            street: Normalizer::text((string) ($data['address'] ?? '')),
            neighborhood: Normalizer::text((string) ($data['district'] ?? '')),
            stateAbbr: $state,
            cityName: $city,
            ibgeCode: isset($data['city_ibge']) ? (int) $data['city_ibge'] : null,
            lat: isset($data['lat']) ? (string) $data['lat'] : null,
            lng: isset($data['lng']) ? (string) $data['lng'] : null,
            providerSlug: $this->getSlug(),
        );
    }
}
