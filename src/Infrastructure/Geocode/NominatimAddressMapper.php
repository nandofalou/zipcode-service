<?php

declare(strict_types=1);

namespace App\Infrastructure\Geocode;

use App\Domain\Dto\NormalizedAddress;
use App\Support\Normalizer;

final class NominatimAddressMapper
{
    public function toNormalizedAddress(array $data, string $lat, string $lng): ?NormalizedAddress
    {
        $address = $data['address'] ?? null;
        if (!is_array($address)) {
            return null;
        }

        $countryCode = strtolower((string) ($address['country_code'] ?? ''));
        if ($countryCode !== 'br') {
            return null;
        }

        $zipcode = Normalizer::zipcode((string) ($address['postcode'] ?? ''));
        if (strlen($zipcode) !== 8) {
            return null;
        }

        $stateAbbr = $this->extractStateAbbr($address);
        $cityName = $this->extractCityName($address);
        if ($stateAbbr === '' || $cityName === null) {
            return null;
        }

        $street = Normalizer::text((string) ($address['road'] ?? $data['name'] ?? ''));
        $neighborhood = Normalizer::text((string) ($address['suburb'] ?? $address['neighbourhood'] ?? ''));

        return new NormalizedAddress(
            zipcode: $zipcode,
            street: $street,
            neighborhood: $neighborhood,
            stateAbbr: $stateAbbr,
            cityName: $cityName,
            ibgeCode: null,
            lat: $lat,
            lng: $lng,
            providerSlug: 'nominatim',
            stateName: Normalizer::text((string) ($address['state'] ?? '')),
        );
    }

    private function extractStateAbbr(array $address): string
    {
        $iso = (string) ($address['ISO3166-2-lvl4'] ?? '');
        if (str_contains($iso, '-')) {
            return Normalizer::stateAbbr(substr($iso, strrpos($iso, '-') + 1));
        }

        return '';
    }

    private function extractCityName(array $address): ?string
    {
        foreach (['city', 'town', 'municipality', 'village'] as $key) {
            $name = Normalizer::text((string) ($address[$key] ?? ''));
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }
}
