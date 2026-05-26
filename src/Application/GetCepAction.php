<?php

declare(strict_types=1);

namespace App\Application;

use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class GetCepAction
{
    public function __construct(private readonly CepLookupService $cepLookupService)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cep = (string) ($args['cep'] ?? '');
        $result = $this->cepLookupService->lookup($cep);

        return JsonResponse::encode($response, $result);
    }
}
