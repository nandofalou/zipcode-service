<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

final class JsonResponse
{
    public static function encode(Response $response, array $data, int $status = 200): Response
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    public static function create(array $data, int $status = 200): Response
    {
        return self::encode(new SlimResponse(), $data, $status);
    }
}
