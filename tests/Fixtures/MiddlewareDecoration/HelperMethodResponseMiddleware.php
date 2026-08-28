<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\MiddlewareDecoration;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Calls $next() first, then conditionally delegates to a separate helper
 * method that constructs a fresh Response. The `new Response(` lives in
 * its own method body, which never calls $next(), so it must not be
 * flagged even though the helper runs after $next() in the call graph.
 */
class HelperMethodResponseMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $response = $next($request);

        if ($response->statusCode() >= 500) {
            return $this->fallbackResponse();
        }

        return $response;
    }

    private function fallbackResponse(): Response
    {
        return new Response(
            body: 'Service Unavailable',
            statusCode: 503,
        );
    }
}
