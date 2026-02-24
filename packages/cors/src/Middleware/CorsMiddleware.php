<?php

declare(strict_types=1);

namespace Marko\Cors\Middleware;

use Marko\Cors\Config\CorsConfig;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CorsConfig $config,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $origin = $request->header('Origin');

        if ($origin === null || !$this->isOriginAllowed($origin)) {
            return $next($request);
        }

        if ($request->method() === 'OPTIONS') {
            $preflightHeaders = [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => implode(', ', $this->config->allowedMethods()),
                'Access-Control-Allow-Headers' => implode(', ', $this->config->allowedHeaders()),
            ];

            if ($this->config->maxAge() > 0) {
                $preflightHeaders['Access-Control-Max-Age'] = (string) $this->config->maxAge();
            }

            return new Response(
                body: '',
                statusCode: 204,
                headers: $preflightHeaders,
            );
        }

        $response = $next($request);
        $corsHeaders = ['Access-Control-Allow-Origin' => $origin];

        if ($this->config->supportsCredentials()) {
            $corsHeaders['Access-Control-Allow-Credentials'] = 'true';
        }

        $headers = array_merge($response->headers(), $corsHeaders);

        return new Response(
            body: $response->body(),
            statusCode: $response->statusCode(),
            headers: $headers,
        );
    }

    private function isOriginAllowed(string $origin): bool
    {
        $allowedOrigins = $this->config->allowedOrigins();

        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $allowedOrigins, true);
    }
}
