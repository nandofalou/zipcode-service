<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Dto\NormalizedAddress;
use App\Domain\Provider\ZipcodeProviderInterface;
use App\Support\Normalizer;

final class OpenCepProvider extends AbstractHttpProvider implements ZipcodeProviderInterface
{
    public function getSlug(): string
    {
        return 'opencep';
    }

    public function fetch(string $digitsOnly): ?NormalizedAddress
    {
        $data = $this->getJson('https://opencep.com/v1/' . $digitsOnly);
        if ($data === null) {
            return null;
        }

        $zipcode = Normalizer::zipcode((string) ($data['cep'] ?? $digitsOnly));
        if (strlen($zipcode) !== 8) {
            return null;
        }

        $state = Normalizer::stateAbbr((string) ($data['uf'] ?? ''));
        $city = Normalizer::text((string) ($data['localidade'] ?? ''));
        if ($state === '' || $city === null) {
            return null;
        }

        return new NormalizedAddress(
            zipcode: $zipcode,
            street: Normalizer::text((string) ($data['logradouro'] ?? '')),
            neighborhood: Normalizer::text((string) ($data['bairro'] ?? '')),
            stateAbbr: $state,
            cityName: $city,
            ibgeCode: isset($data['ibge']) && $data['ibge'] !== '' ? (int) $data['ibge'] : null,
            lat: null,
            lng: null,
            providerSlug: $this->getSlug(),
        );
    }
}
