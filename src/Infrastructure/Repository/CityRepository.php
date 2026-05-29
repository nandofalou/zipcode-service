<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Support\Normalizer;
use PDO;

final class CityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findBySlug(int $stateId, string $normalizedName): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM city WHERE state_id = :state_id AND normalized_name = :normalized_name LIMIT 1'
        );
        $stmt->execute([
            'state_id' => $stateId,
            'normalized_name' => $normalizedName,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByIbgeCode(int $ibgeCode): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM city WHERE ibge_code = :ibge_code LIMIT 1');
        $stmt->execute(['ibge_code' => $ibgeCode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array{created: bool, updated: bool, city: array<string, mixed>} */
    public function upsertFromIbge(int $stateId, int $ibgeCode, string $name): array
    {
        $name = Normalizer::text($name) ?? 'Unknown';
        $normalizedName = Normalizer::citySlug($name);
        $existing = $this->findByIbgeCode($ibgeCode);

        if ($existing === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO city (state_id, ibge_code, name, latitude, longitude, normalized_name)
                 VALUES (:state_id, :ibge_code, :name, NULL, NULL, :normalized_name)'
            );
            $stmt->execute([
                'state_id' => $stateId,
                'ibge_code' => $ibgeCode,
                'name' => $name,
                'normalized_name' => $normalizedName,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $city = $this->findById($id) ?? [
                'id' => $id,
                'state_id' => $stateId,
                'ibge_code' => $ibgeCode,
                'name' => $name,
                'normalized_name' => $normalizedName,
            ];

            return ['created' => true, 'updated' => false, 'city' => $city];
        }

        $needsUpdate = (int) $existing['state_id'] !== $stateId
            || $existing['name'] !== $name
            || $existing['normalized_name'] !== $normalizedName;

        if (!$needsUpdate) {
            return ['created' => false, 'updated' => false, 'city' => $existing];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE city SET state_id = :state_id, name = :name, normalized_name = :normalized_name
             WHERE ibge_code = :ibge_code'
        );
        $stmt->execute([
            'state_id' => $stateId,
            'name' => $name,
            'normalized_name' => $normalizedName,
            'ibge_code' => $ibgeCode,
        ]);

        $city = $this->findByIbgeCode($ibgeCode) ?? $existing;

        return ['created' => false, 'updated' => true, 'city' => $city];
    }

    public function findOrCreate(
        int $stateId,
        string $name,
        ?int $ibgeCode = null,
        ?string $lat = null,
        ?string $lng = null,
    ): array {
        $name = Normalizer::text($name) ?? 'Unknown';
        $normalizedName = Normalizer::citySlug($name);
        $existing = $this->findBySlug($stateId, $normalizedName);

        if ($existing !== null) {
            if ($this->shouldUpdate($existing, $ibgeCode, $lat, $lng)) {
                $this->update($existing['id'], $ibgeCode, $lat, $lng);

                return $this->findById((int) $existing['id']) ?? $existing;
            }

            return $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO city (state_id, ibge_code, name, latitude, longitude, normalized_name)
             VALUES (:state_id, :ibge_code, :name, :latitude, :longitude, :normalized_name)'
        );
        $stmt->execute([
            'state_id' => $stateId,
            'ibge_code' => $ibgeCode,
            'name' => $name,
            'latitude' => $lat !== null ? (float) $lat : null,
            'longitude' => $lng !== null ? (float) $lng : null,
            'normalized_name' => $normalizedName,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id) ?? [
            'id' => $id,
            'state_id' => $stateId,
            'ibge_code' => $ibgeCode,
            'name' => $name,
            'latitude' => $lat,
            'longitude' => $lng,
            'normalized_name' => $normalizedName,
        ];
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM city WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function shouldUpdate(array $city, ?int $ibgeCode, ?string $lat, ?string $lng): bool
    {
        if ($ibgeCode !== null && empty($city['ibge_code'])) {
            return true;
        }
        if ($lat !== null && empty($city['latitude'])) {
            return true;
        }
        if ($lng !== null && empty($city['longitude'])) {
            return true;
        }

        return false;
    }

    private function update(int $id, ?int $ibgeCode, ?string $lat, ?string $lng): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE city SET
                ibge_code = COALESCE(:ibge_code, ibge_code),
                latitude = COALESCE(:latitude, latitude),
                longitude = COALESCE(:longitude, longitude)
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'ibge_code' => $ibgeCode,
            'latitude' => $lat !== null ? (float) $lat : null,
            'longitude' => $lng !== null ? (float) $lng : null,
        ]);
    }
}
