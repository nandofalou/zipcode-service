<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Dto\NormalizedAddress;
use App\Domain\Provider\ZipcodeProviderInterface;
use App\Support\Normalizer;

final class BrasilApiV2Provider extends AbstractHttpProvider implements ZipcodeProviderInterface
{
    public function getSlug(): string
    {
        return 'brasilapi-v2';
    }

    public function fetch(string $digitsOnly): ?NormalizedAddress
    {
        $data = $this->getJson('https://brasilapi.com.br/api/cep/v2/' . $digitsOnly);
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

        $lat = null;
        $lng = null;
        if (isset($data['location']['coordinates']) && is_array($data['location']['coordinates'])) {
            $coords = $data['location']['coordinates'];
            if (isset($coords['longitude'], $coords['latitude'])) {
                $lng = (string) $coords['longitude'];
                $lat = (string) $coords['latitude'];
            } elseif (count($coords) >= 2) {
                $lng = (string) $coords[0];
                $lat = (string) $coords[1];
            }
        }

        return new NormalizedAddress(
            zipcode: $zipcode,
            street: Normalizer::text((string) ($data['street'] ?? '')),
            neighborhood: Normalizer::text((string) ($data['neighborhood'] ?? '')),
            stateAbbr: $state,
            cityName: $city,
            ibgeCode: null,
            lat: $lat,
            lng: $lng,
            providerSlug: $this->getSlug(),
        );
    }
}
