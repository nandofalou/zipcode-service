<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Infrastructure\Ibge\IbgeLocalidadesClient;
use App\Infrastructure\Repository\CityRepository;
use App\Infrastructure\Repository\CountryRepository;
use App\Infrastructure\Repository\StateRepository;
use App\Support\Normalizer;
use PDO;
use RuntimeException;

final class IbgeLocalidadesImporter
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly IbgeLocalidadesClient $ibgeClient,
        private readonly CountryRepository $countryRepository,
        private readonly StateRepository $stateRepository,
        private readonly CityRepository $cityRepository,
        private readonly array $defaultCountry,
    ) {
    }

    /** @return array<string, mixed> */
    public function import(bool $dryRun = false, ?string $stateFilter = null): array
    {
        $startedAt = microtime(true);
        $stats = [
            'states_fetched' => 0,
            'states_inserted' => 0,
            'states_skipped' => 0,
            'cities_created' => 0,
            'cities_updated' => 0,
            'cities_unchanged' => 0,
            'by_state' => [],
            'errors' => [],
        ];

        $country = $this->countryRepository->findOrCreateDefault($this->defaultCountry);
        $countryId = (int) $country['id'];

        $states = $this->ibgeClient->fetchStates();
        if ($states === []) {
            throw new RuntimeException('Não foi possível obter estados do IBGE.');
        }

        if ($stateFilter !== null) {
            $stateFilter = Normalizer::stateAbbr($stateFilter);
            $states = array_values(array_filter(
                $states,
                static fn (array $s): bool => Normalizer::stateAbbr((string) ($s['sigla'] ?? '')) === $stateFilter
            ));
            if ($states === []) {
                throw new RuntimeException("UF não encontrada no IBGE: {$stateFilter}");
            }
        }

        $stats['states_fetched'] = count($states);

        foreach ($states as $stateData) {
            $abbr = Normalizer::stateAbbr((string) ($stateData['sigla'] ?? ''));
            $name = (string) ($stateData['nome'] ?? '');
            $ibgeStateId = (int) ($stateData['id'] ?? 0);

            if ($abbr === '' || $ibgeStateId <= 0) {
                $stats['errors'][] = 'Estado IBGE inválido ignorado.';
                continue;
            }

            if ($dryRun) {
                $existing = $this->stateRepository->findByAbbr($countryId, $abbr);
                if ($existing === null) {
                    ++$stats['states_inserted'];
                } else {
                    ++$stats['states_skipped'];
                }
                $stateId = (int) ($existing['id'] ?? 0);
            } else {
                $result = $this->stateRepository->insertIfNotExists($countryId, $abbr, $name, $ibgeStateId);
                if ($result['inserted']) {
                    ++$stats['states_inserted'];
                } else {
                    ++$stats['states_skipped'];
                }
                $stateId = (int) $result['state']['id'];
            }

            $municipalities = $this->ibgeClient->fetchMunicipalities($ibgeStateId);
            if ($municipalities === []) {
                $stats['errors'][] = "Municípios não obtidos para UF {$abbr} (IBGE {$ibgeStateId}).";
                continue;
            }

            $stateStats = [
                'fetched' => count($municipalities),
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
            ];

            if (!$dryRun) {
                $this->pdo->beginTransaction();
            }

            try {
                foreach ($municipalities as $cityData) {
                    $cityName = (string) ($cityData['nome'] ?? '');
                    $ibgeCityId = (int) ($cityData['id'] ?? 0);

                    if ($cityName === '' || $ibgeCityId <= 0) {
                        continue;
                    }

                    if ($dryRun) {
                        $existing = $this->cityRepository->findByIbgeCode($ibgeCityId);
                        $normalizedName = Normalizer::citySlug($cityName);
                        $cityNameNorm = Normalizer::text($cityName) ?? 'Unknown';
                        if ($existing === null) {
                            ++$stateStats['created'];
                        } elseif ($existing['name'] !== $cityNameNorm || $existing['normalized_name'] !== $normalizedName) {
                            ++$stateStats['updated'];
                        } else {
                            ++$stateStats['unchanged'];
                        }
                        continue;
                    }

                    $upsert = $this->cityRepository->upsertFromIbge($stateId, $ibgeCityId, $cityName);
                    if ($upsert['created']) {
                        ++$stateStats['created'];
                    } elseif ($upsert['updated']) {
                        ++$stateStats['updated'];
                    } else {
                        ++$stateStats['unchanged'];
                    }
                }

                if (!$dryRun) {
                    $this->pdo->commit();
                }
            } catch (\Throwable $e) {
                if (!$dryRun && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }

            $stats['cities_created'] += $stateStats['created'];
            $stats['cities_updated'] += $stateStats['updated'];
            $stats['cities_unchanged'] += $stateStats['unchanged'];
            $stats['by_state'][$abbr] = $stateStats;
        }

        $stats['elapsed_seconds'] = round(microtime(true) - $startedAt, 2);
        $stats['dry_run'] = $dryRun;

        return $stats;
    }
}
