<?php

declare(strict_types=1);

namespace Marko\Security\Middleware;

use Marko\Routing\Attributes\RunsOnUnmatched;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;

/**
 * Adds the configured security headers to every response.
 *
 * Registered globally by marko/security's module.php; opt a route out with
 * #[WithoutMiddleware(SecurityHeadersMiddleware::class)]. A header the
 * controller (or an inner middleware) already set is left untouched, so a
 * route can send a stricter or different value. Strict-Transport-Security is
 * only sent on HTTPS responses, where browsers honour it.
 */
#[RunsOnUnmatched]
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

        $existing = array_change_key_case($response->headers());
        $missing = array_filter(
            $this->buildSecurityHeaders($request),
            static fn (string $name): bool => !array_key_exists(strtolower($name), $existing),
            ARRAY_FILTER_USE_KEY,
        );

        return $missing === [] ? $response : $response->withHeaders($missing);
    }

    /**
     * @return array<string, string>
     */
    private function buildSecurityHeaders(
        Request $request,
    ): array {
        $headerMap = [
            'X-Content-Type-Options' => $this->securityConfig->headerXContentTypeOptions(),
            'X-Frame-Options' => $this->securityConfig->headerXFrameOptions(),
            'X-XSS-Protection' => $this->securityConfig->headerXXssProtection(),
            'Strict-Transport-Security' => $this->isHttps($request)
                ? $this->securityConfig->headerStrictTransportSecurity()
                : '',
            'Referrer-Policy' => $this->securityConfig->headerReferrerPolicy(),
            'Content-Security-Policy' => $this->securityConfig->headerContentSecurityPolicy(),
        ];

        return array_filter($headerMap, static fn (string $value): bool => $value !== '');
    }

    /**
     * Whether the browser reached the application over HTTPS.
     *
     * X-Forwarded-Proto is honoured so HSTS reaches browsers behind a
     * TLS-terminating proxy. Trusting it here is safe: a client that forges it
     * on a plain-HTTP request only receives an HSTS header that browsers ignore
     * on insecure responses.
     */
    private function isHttps(
        Request $request,
    ): bool {
        $https = $request->server('HTTPS');

        if (
            $https !== null
            && $https !== ''
            && strtolower($https) !== 'off'
        ) {
            return true;
        }

        if (strtolower($request->server('REQUEST_SCHEME') ?? '') === 'https') {
            return true;
        }

        $forwardedProto = explode(',', $request->header('X-Forwarded-Proto') ?? '')[0];

        return strtolower(trim($forwardedProto)) === 'https';
    }
}
