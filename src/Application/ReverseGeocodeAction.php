<?php

declare(strict_types=1);

namespace App\Application;

use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ReverseGeocodeAction
{
    public function __construct(private readonly ReverseGeocodeService $reverseGeocodeService)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = strtoupper($request->getMethod()) === 'GET'
            ? $request->getQueryParams()
            : (array) ($request->getParsedBody() ?? []);

        if (!array_key_exists('lat', $params) || !array_key_exists('lng', $params)) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'Campos lat e lng são obrigatórios.',
            ]);
        }

        if (!is_numeric($params['lat']) || !is_numeric($params['lng'])) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'Campos lat e lng devem ser numéricos.',
            ]);
        }

        $result = $this->reverseGeocodeService->reverse((float) $params['lat'], (float) $params['lng']);

        return JsonResponse::encode($response, $result);
    }
}
