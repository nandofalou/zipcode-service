<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Geocode\NominatimAddressMapper;
use App\Infrastructure\Geocode\NominatimClient;

final class ReverseGeocodeService
{
    public function __construct(
        private readonly NominatimClient $nominatimClient,
        private readonly NominatimAddressMapper $nominatimMapper,
        private readonly CepLookupService $cepLookupService,
    ) {
    }

    public function reverse(float $lat, float $lng): array
    {
        if ($lat < -90 || $lat > 90) {
            return ['status' => false, 'message' => 'Latitude inválida. Informe um valor entre -90 e 90.'];
        }
        if ($lng < -180 || $lng > 180) {
            return ['status' => false, 'message' => 'Longitude inválida. Informe um valor entre -180 e 180.'];
        }

        $nominatim = $this->nominatimClient->reverse($lat, $lng);
        if ($nominatim === null) {
            return ['status' => false, 'message' => 'Não foi possível consultar o Nominatim.'];
        }

        $latStr = (string) $lat;
        $lngStr = (string) $lng;

        $fallbackAddress = $this->nominatimMapper->toNormalizedAddress($nominatim, $latStr, $lngStr);
        if ($fallbackAddress === null) {
            return ['status' => false, 'message' => 'Endereço não encontrado ou fora do Brasil.'];
        }

        $result = $this->cepLookupService->lookup($fallbackAddress->zipcode);
        if (($result['status'] ?? false) !== true) {
            $result = $this->cepLookupService->saveAndFormat($fallbackAddress);
        }

        if (($result['status'] ?? false) === true) {
            $result['lat'] = $latStr;
            $result['lng'] = $lngStr;
        }

        return $result;
    }
}
