<?php

declare(strict_types=1);

namespace Marko\Security\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SecurityConfig $config,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $origin = $request->header('Origin');

        if ($origin === null) {
            return $next($request);
        }

        if (!$this->isAllowedOrigin($origin)) {
            return $next($request);
        }

        // Preflight OPTIONS request -- short-circuit with 204
        if ($request->method() === 'OPTIONS') {
            return new Response(
                body: '',
                statusCode: 204,
                headers: $this->buildPreflightHeaders($origin),
            );
        }

        /** @var Response $response */
        $response = $next($request);

        return new Response(
            body: $response->body(),
            statusCode: $response->statusCode(),
            headers: array_merge($response->headers(), [
                'Access-Control-Allow-Origin' => $origin,
            ]),
        );
    }

    private function isAllowedOrigin(
        string $origin,
    ): bool {
        $allowedOrigins = $this->config->corsAllowedOrigins();

        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $allowedOrigins, true);
    }

    /**
     * @return array<string, string>
     */
    private function buildPreflightHeaders(
        string $origin,
    ): array {
        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => implode(', ', $this->config->corsAllowedMethods()),
            'Access-Control-Allow-Headers' => implode(', ', $this->config->corsAllowedHeaders()),
            'Access-Control-Max-Age' => (string) $this->config->corsMaxAge(),
        ];
    }
}
