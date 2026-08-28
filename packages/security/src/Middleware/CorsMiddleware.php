<?php

declare(strict_types=1);

namespace Marko\Security\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;

readonly class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SecurityConfig $securityConfig,
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

        return $response->withHeader('Access-Control-Allow-Origin', $origin);
    }

    private function isAllowedOrigin(
        string $origin,
    ): bool {
        $allowedOrigins = $this->securityConfig->corsAllowedOrigins();

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
            'Access-Control-Allow-Methods' => implode(', ', $this->securityConfig->corsAllowedMethods()),
            'Access-Control-Allow-Headers' => implode(', ', $this->securityConfig->corsAllowedHeaders()),
            'Access-Control-Max-Age' => (string) $this->securityConfig->corsMaxAge(),
        ];
    }
}
