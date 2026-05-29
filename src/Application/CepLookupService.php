<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Dto\NormalizedAddress;
use App\Infrastructure\Provider\ZipcodeProviderChain;
use App\Infrastructure\Repository\CityRepository;
use App\Infrastructure\Repository\CountryRepository;
use App\Infrastructure\Repository\StateRepository;
use App\Infrastructure\Repository\ZipcodeRepository;

final class CepLookupService
{
    public function __construct(
        private readonly ZipcodeRepository $zipcodeRepository,
        private readonly CountryRepository $countryRepository,
        private readonly StateRepository $stateRepository,
        private readonly CityRepository $cityRepository,
        private readonly ZipcodeProviderChain $providerChain,
        private readonly array $defaultCountry,
    ) {
    }

    public function lookup(string $cep): array
    {
        $digits = preg_replace('/\D/', '', $cep) ?? '';
        if (strlen($digits) !== 8) {
            return [
                'status' => false,
                'message' => 'CEP inválido. Informe 8 dígitos.',
            ];
        }

        $cached = $this->zipcodeRepository->findByZipcode($digits);
        if ($cached !== null) {
            return $this->formatSuccess($cached);
        }

        $address = $this->providerChain->fetch($digits);
        if ($address === null) {
            return [
                'status' => false,
                'message' => 'CEP não encontrado.',
            ];
        }

        return $this->saveAndFormat($address);
    }

    public function saveAndFormat(NormalizedAddress $address): array
    {
        $this->persist($address);

        $saved = $this->zipcodeRepository->findByZipcode($address->zipcode);
        if ($saved === null) {
            return [
                'status' => false,
                'message' => 'Erro ao salvar CEP.',
            ];
        }

        return $this->formatSuccess($saved);
    }

    public function formatSuccess(array $row, ?string $latOverride = null, ?string $lngOverride = null): array
    {
        $lat = $latOverride ?? ($row['latitude'] ?? $row['city_latitude'] ?? null);
        $lng = $lngOverride ?? ($row['longitude'] ?? $row['city_longitude'] ?? null);

        return [
            'status' => true,
            'message' => '',
            'zipcode' => $row['zipcode'],
            'street' => $row['street'] ?? '',
            'neighborhood' => $row['neighborhood'] ?? '',
            'lat' => $lat !== null && $lat !== '' ? (string) $lat : '',
            'lng' => $lng !== null && $lng !== '' ? (string) $lng : '',
            'city' => [
                'id' => (int) $row['city_id'],
                'name' => $row['city_name'],
                'ibge_code' => $row['city_ibge_code'] !== null ? (int) $row['city_ibge_code'] : null,
            ],
            'state' => [
                'id' => (int) $row['state_id'],
                'abbr' => $row['state_abbr'],
            ],
        ];
    }

    private function persist(NormalizedAddress $address): void
    {
        $country = $this->countryRepository->findOrCreateDefault($this->defaultCountry);
        $state = $this->stateRepository->findOrCreateByAbbr(
            (int) $country['id'],
            $address->stateAbbr,
            $address->stateName,
        );
        $city = $this->cityRepository->findOrCreate(
            (int) $state['id'],
            $address->cityName,
            $address->ibgeCode,
            $address->lat,
            $address->lng,
        );
        try {
            $this->zipcodeRepository->insert($address->zipcode, (int) $city['id'], $address);
        } catch (\PDOException) {
            // Concorrência: outro request pode ter gravado o mesmo CEP.
        }
    }
}
