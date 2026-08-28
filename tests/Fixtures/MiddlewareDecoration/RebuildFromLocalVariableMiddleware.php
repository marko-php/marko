<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\MiddlewareDecoration;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Deliberately reproduces the original InertiaMiddleware bug: headers are
 * copied off the response $next() returned into a local variable, mutated,
 * and then fed to a freshly constructed Response. The variable indirection
 * defeats a detector that only matches constructor arguments directly
 * against another response's accessors -- this fixture proves the
 * position-based rule catches it anyway.
 */
class RebuildFromLocalVariableMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $response = $next($request);

        $headers = $response->headers();
        $headers['Vary'] = 'X-Custom';

        return new Response(
            body: $response->body(),
            statusCode: $response->statusCode(),
            headers: $headers,
        );
    }
}
