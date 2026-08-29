<?php

declare(strict_types=1);

namespace Marko\Security\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;

readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SecurityConfig $securityConfig,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        /** @var Response $response */
        $response = $next($request);

        return $response->withHeaders($this->buildSecurityHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function buildSecurityHeaders(): array
    {
        $headerMap = [
            'X-Content-Type-Options' => $this->securityConfig->headerXContentTypeOptions(),
            'X-Frame-Options' => $this->securityConfig->headerXFrameOptions(),
            'X-XSS-Protection' => $this->securityConfig->headerXXssProtection(),
            'Strict-Transport-Security' => $this->securityConfig->headerStrictTransportSecurity(),
            'Referrer-Policy' => $this->securityConfig->headerReferrerPolicy(),
            'Content-Security-Policy' => $this->securityConfig->headerContentSecurityPolicy(),
        ];

        return array_filter($headerMap, static fn (string $value): bool => $value !== '');
    }
}
