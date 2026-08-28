<?php

declare(strict_types=1);

namespace Marko\Cors\Middleware;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Cors\Config\CorsConfig;
use Marko\Cors\Exceptions\CorsException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

readonly class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CorsConfig $corsConfig,
    ) {}

    /**
     * @throws ConfigNotFoundException|CorsException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $origin = $request->header('Origin');

        if ($origin === null || !$this->isOriginAllowed($origin)) {
            return $next($request);
        }

        if ($this->corsConfig->supportsCredentials() && in_array('*', $this->corsConfig->allowedOrigins(), true)) {
            throw CorsException::wildcardWithCredentials();
        }

        if ($request->method() === 'OPTIONS') {
            $preflightHeaders = [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => implode(', ', $this->corsConfig->allowedMethods()),
                'Access-Control-Allow-Headers' => implode(', ', $this->corsConfig->allowedHeaders()),
                'Vary' => 'Origin',
            ];

            if ($this->corsConfig->maxAge() > 0) {
                $preflightHeaders['Access-Control-Max-Age'] = (string) $this->corsConfig->maxAge();
            }

            return new Response(
                body: '',
                statusCode: 204,
                headers: $preflightHeaders,
            );
        }

        /** @var Response $response */
        $response = $next($request);
        $corsHeaders = [
            'Access-Control-Allow-Origin' => $origin,
            'Vary' => 'Origin',
        ];

        if ($this->corsConfig->supportsCredentials()) {
            $corsHeaders['Access-Control-Allow-Credentials'] = 'true';
        }

        return $response->withHeaders($corsHeaders);
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function isOriginAllowed(string $origin): bool
    {
        $allowedOrigins = $this->corsConfig->allowedOrigins();

        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $allowedOrigins, true);
    }
}
