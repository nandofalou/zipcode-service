<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Infrastructure\Repository\ServiceAccountRepository;
use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ListServiceAccountsAction
{
    public function __construct(private readonly ServiceAccountRepository $repository)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponse::encode($response, [
            'status' => true,
            'message' => '',
            'data' => $this->repository->listAll(),
        ]);
    }
}
