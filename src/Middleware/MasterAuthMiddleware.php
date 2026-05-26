<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MasterAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $account = $request->getAttribute(ServiceAuthMiddleware::ATTRIBUTE_ACCOUNT);
        if (!is_array($account) || (int) ($account['is_master'] ?? 0) !== 1) {
            return JsonResponse::create([
                'status' => false,
                'message' => 'Acesso restrito a contas master.',
            ], 403);
        }

        return $handler->handle($request);
    }
}
