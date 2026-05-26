<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Dto\NormalizedAddress;
use App\Domain\Provider\ZipcodeProviderInterface;
use App\Support\Normalizer;

final class ApiCepProvider extends AbstractHttpProvider implements ZipcodeProviderInterface
{
    public function getSlug(): string
    {
        return 'apicep';
    }

    public function fetch(string $digitsOnly): ?NormalizedAddress
    {
        $masked = Normalizer::maskedZipcode($digitsOnly);
        $data = $this->getJson('https://cdn.apicep.com/file/apicep/' . $masked . '.json');
        if ($data === null || $this->isCepError($data)) {
            return null;
        }

        $zipcode = Normalizer::zipcode((string) ($data['code'] ?? $digitsOnly));
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
            ibgeCode: null,
            lat: null,
            lng: null,
            providerSlug: $this->getSlug(),
        );
    }
}
