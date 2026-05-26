<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Dto\NormalizedAddress;
use App\Support\Normalizer;
use DateTimeImmutable;
use PDO;

final class ZipcodeRepository
{
    private const SORT_COLUMNS = [
        'zipcode' => 'z.zipcode',
        'city' => 'c.name',
        'neighborhood' => 'z.neighborhood',
        'state' => 's.abbr',
        'created_at' => 'z.created_at',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByZipcode(string $zipcode): ?array
    {
        $zipcode = Normalizer::zipcode($zipcode);
        $stmt = $this->pdo->prepare(
            'SELECT z.*, c.id AS city_id, c.name AS city_name, c.ibge_code AS city_ibge_code,
                    c.latitude AS city_latitude, c.longitude AS city_longitude,
                    s.id AS state_id, s.abbr AS state_abbr, s.name AS state_name
             FROM zipcode z
             INNER JOIN city c ON c.id = z.city_id
             INNER JOIN state s ON s.id = c.state_id
             WHERE z.zipcode = :zipcode
             LIMIT 1'
        );
        $stmt->execute(['zipcode' => $zipcode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function insert(
        string $zipcode,
        int $cityId,
        NormalizedAddress $address,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO zipcode (zipcode, city_id, street, neighborhood, provider, latitude, longitude, created_at)
             VALUES (:zipcode, :city_id, :street, :neighborhood, :provider, :latitude, :longitude, :created_at)'
        );
        $stmt->execute([
            'zipcode' => Normalizer::zipcode($zipcode),
            'city_id' => $cityId,
            'street' => $address->street,
            'neighborhood' => $address->neighborhood,
            'provider' => $address->providerSlug,
            'latitude' => $address->lat !== null ? (float) $address->lat : null,
            'longitude' => $address->lng !== null ? (float) $address->lng : null,
            'created_at' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ]);
    }

    public function searchPaginated(ZipcodeListQuery $query): array
    {
        [$where, $params] = $this->buildListFilters($query);

        $countSql = 'SELECT COUNT(*) FROM zipcode z
            INNER JOIN city c ON c.id = z.city_id
            INNER JOIN state s ON s.id = c.state_id' . $where;
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sortColumn = self::SORT_COLUMNS[$query->sortBy] ?? self::SORT_COLUMNS['created_at'];
        $sortOrder = strtoupper($query->sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ($query->page - 1) * $query->perPage;

        $sql = 'SELECT z.zipcode, z.street, z.neighborhood, z.provider, z.latitude, z.longitude, z.created_at,
                    c.id AS city_id, c.name AS city_name, c.ibge_code AS city_ibge_code,
                    s.id AS state_id, s.abbr AS state_abbr, s.name AS state_name
             FROM zipcode z
             INNER JOIN city c ON c.id = z.city_id
             INNER JOIN state s ON s.id = c.state_id'
            . $where
            . " ORDER BY {$sortColumn} {$sortOrder}"
            . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $query->perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map([$this, 'formatListItem'], $stmt->fetchAll());

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function deleteByZipcode(string $zipcode): bool
    {
        $zipcode = Normalizer::zipcode($zipcode);
        if (strlen($zipcode) !== 8) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM zipcode WHERE zipcode = :zipcode');
        $stmt->execute(['zipcode' => $zipcode]);

        return $stmt->rowCount() > 0;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildListFilters(ZipcodeListQuery $query): array
    {
        $conditions = [];
        $params = [];

        if ($query->city !== null && $query->city !== '') {
            $conditions[] = 'LOWER(c.name) LIKE LOWER(:city)';
            $params['city'] = '%' . $query->city . '%';
        }

        if ($query->neighborhood !== null && $query->neighborhood !== '') {
            $conditions[] = 'LOWER(z.neighborhood) LIKE LOWER(:neighborhood)';
            $params['neighborhood'] = '%' . $query->neighborhood . '%';
        }

        if ($query->stateAbbr !== null && $query->stateAbbr !== '') {
            $conditions[] = 's.abbr = :state_abbr';
            $params['state_abbr'] = Normalizer::stateAbbr($query->stateAbbr);
        }

        if ($query->zipcodePartial !== null && $query->zipcodePartial !== '') {
            $digits = Normalizer::digitsOnly($query->zipcodePartial);
            if ($digits !== '') {
                $conditions[] = 'z.zipcode LIKE :zipcode_partial';
                $params['zipcode_partial'] = $digits . '%';
            }
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    private function formatListItem(array $row): array
    {
        return [
            'zipcode' => $row['zipcode'],
            'street' => $row['street'] ?? '',
            'neighborhood' => $row['neighborhood'] ?? '',
            'lat' => $row['latitude'] !== null ? (string) $row['latitude'] : '',
            'lng' => $row['longitude'] !== null ? (string) $row['longitude'] : '',
            'provider' => $row['provider'],
            'created_at' => $row['created_at'],
            'city' => [
                'id' => (int) $row['city_id'],
                'name' => $row['city_name'],
                'ibge_code' => $row['city_ibge_code'] !== null ? (int) $row['city_ibge_code'] : null,
            ],
            'state' => [
                'id' => (int) $row['state_id'],
                'abbr' => $row['state_abbr'],
                'name' => $row['state_name'],
            ],
        ];
    }
}
