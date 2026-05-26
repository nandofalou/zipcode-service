<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Repository\ServiceAccountRepository;
use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ServiceAuthMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE_ACCOUNT = 'service_account';

    public function __construct(private readonly ServiceAccountRepository $repository)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $serviceKey = trim($request->getHeaderLine('X-Service-Key'));
        $serviceToken = trim($request->getHeaderLine('X-Service-Token'));

        if ($serviceKey === '' || $serviceToken === '') {
            return JsonResponse::create([
                'status' => false,
                'message' => 'Autenticação obrigatória (X-Service-Key e X-Service-Token).',
            ], 401);
        }

        $account = $this->repository->findActiveByCredentials($serviceKey, $serviceToken);
        if ($account === null) {
            return JsonResponse::create([
                'status' => false,
                'message' => 'Credenciais inválidas ou conta inativa.',
            ], 401);
        }

        return $handler->handle($request->withAttribute(self::ATTRIBUTE_ACCOUNT, $account));
    }
}
