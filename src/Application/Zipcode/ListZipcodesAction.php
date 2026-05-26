<?php

declare(strict_types=1);

namespace App\Application\Zipcode;

use App\Infrastructure\Repository\ZipcodeListQuery;
use App\Infrastructure\Repository\ZipcodeRepository;
use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ListZipcodesAction
{
    private const ALLOWED_SORT = ['zipcode', 'city', 'neighborhood', 'state', 'created_at'];

    public function __construct(private readonly ZipcodeRepository $repository)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));

        $sortBy = (string) ($params['sort'] ?? 'created_at');
        if (!in_array($sortBy, self::ALLOWED_SORT, true)) {
            $sortBy = 'created_at';
        }

        $sortOrder = strtolower((string) ($params['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = new ZipcodeListQuery(
            city: $this->optionalString($params, 'city'),
            neighborhood: $this->optionalString($params, 'neighborhood'),
            stateAbbr: $this->optionalString($params, 'state'),
            zipcodePartial: $this->optionalString($params, 'zipcode'),
            page: $page,
            perPage: $perPage,
            sortBy: $sortBy,
            sortOrder: $sortOrder,
        );

        $result = $this->repository->searchPaginated($query);
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        return JsonResponse::encode($response, [
            'status' => true,
            'message' => '',
            'data' => $result['items'],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'sort' => $sortBy,
                'order' => $sortOrder,
            ],
        ]);
    }

    private function optionalString(array $params, string $key): ?string
    {
        if (!isset($params[$key])) {
            return null;
        }

        $value = trim((string) $params[$key]);

        return $value === '' ? null : $value;
    }
}
