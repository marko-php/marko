<?php

declare(strict_types=1);

namespace Marko\Security\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SecurityConfig $config,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        /** @var Response $response */
        $response = $next($request);

        $securityHeaders = $this->buildSecurityHeaders();

        return new Response(
            body: $response->body(),
            statusCode: $response->statusCode(),
            headers: array_merge($response->headers(), $securityHeaders),
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildSecurityHeaders(): array
    {
        $headerMap = [
            'X-Content-Type-Options' => $this->config->headerXContentTypeOptions(),
            'X-Frame-Options' => $this->config->headerXFrameOptions(),
            'X-XSS-Protection' => $this->config->headerXXssProtection(),
            'Strict-Transport-Security' => $this->config->headerStrictTransportSecurity(),
            'Referrer-Policy' => $this->config->headerReferrerPolicy(),
            'Content-Security-Policy' => $this->config->headerContentSecurityPolicy(),
        ];

        return array_filter($headerMap, static fn (string $value): bool => $value !== '');
    }
}
