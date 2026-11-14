<?php

declare(strict_types=1);

namespace Marko\Cors\Middleware;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Cors\Config\CorsConfig;
use Marko\Cors\Exceptions\CorsException;
use Marko\Routing\Attributes\RunsOnUnmatched;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Registered as global middleware by marko/cors, so it runs on every request
 * and outermost of the framework's global middleware. #[RunsOnUnmatched]
 * keeps it running when no route matches: a preflight to a path without an
 * explicit OPTIONS route is an unmatched request. It does nothing unless the
 * request carries an allowed `Origin` and its path matches `cors.paths`.
 *
 * A preflight (`OPTIONS` with `Access-Control-Request-Method`) is answered
 * here with a 204, whether or not a route exists for the path. Every other
 * request continues down the pipeline and has the CORS headers added to
 * whatever response comes back, error responses (404, 405) included.
 */
#[RunsOnUnmatched]
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

        if (
            $origin === null
            || !$this->isPathCovered($request->path())
            || !$this->isOriginAllowed($origin)
        ) {
            return $next($request);
        }

        if (
            $this->corsConfig->supportsCredentials()
            && in_array('*', $this->corsConfig->allowedOrigins(), true)
        ) {
            throw CorsException::wildcardWithCredentials();
        }

        if ($this->isPreflight($request)) {
            return $this->preflightResponse($origin);
        }

        /** @var Response $response */
        $response = $next($request);
        $corsHeaders = [
            'Access-Control-Allow-Origin' => $origin,
            'Vary' => $this->varyWithOrigin($response),
        ];

        if ($this->corsConfig->supportsCredentials()) {
            $corsHeaders['Access-Control-Allow-Credentials'] = 'true';
        }

        $exposeHeaders = $this->corsConfig->exposeHeaders();

        if ($exposeHeaders !== []) {
            $corsHeaders['Access-Control-Expose-Headers'] = implode(', ', $exposeHeaders);
        }

        return $response->withHeaders($corsHeaders);
    }

    private function isPreflight(
        Request $request,
    ): bool {
        return $request->method() === 'OPTIONS'
            && $request->header('Access-Control-Request-Method') !== null;
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function preflightResponse(
        string $origin,
    ): Response {
        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => implode(', ', $this->corsConfig->allowedMethods()),
            'Access-Control-Allow-Headers' => implode(', ', $this->corsConfig->allowedHeaders()),
            'Vary' => 'Origin',
        ];

        if ($this->corsConfig->supportsCredentials()) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if ($this->corsConfig->maxAge() > 0) {
            $headers['Access-Control-Max-Age'] = (string) $this->corsConfig->maxAge();
        }

        return new Response(
            body: '',
            statusCode: 204,
            headers: $headers,
        );
    }

    private function varyWithOrigin(
        Response $response,
    ): string {
        $vary = $response->headers()['Vary'] ?? '';

        if ($vary === '') {
            return 'Origin';
        }

        $values = array_map(trim(...), explode(',', $vary));

        return in_array('origin', array_map(strtolower(...), $values), true) ? $vary : "$vary, Origin";
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function isPathCovered(
        string $path,
    ): bool {
        $path = ltrim($path, '/');

        return array_any(
            $this->corsConfig->paths(),
            fn (string $pattern): bool => preg_match(
                '#^' . str_replace('\*', '.*', preg_quote(ltrim($pattern, '/'), '#')) . '$#',
                $path,
            ) === 1,
        );
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function isOriginAllowed(
        string $origin,
    ): bool {
        $allowedOrigins = $this->corsConfig->allowedOrigins();

        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $allowedOrigins, true);
    }
}
