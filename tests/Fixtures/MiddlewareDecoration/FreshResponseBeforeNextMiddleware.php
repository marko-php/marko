<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\MiddlewareDecoration;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Constructs a genuinely fresh Response before calling $next() at all --
 * a short-circuit response, not a rebuild. Must not be flagged.
 */
class FreshResponseBeforeNextMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if ($request->header('X-Skip') !== null) {
            return new Response(
                body: '',
                statusCode: 204,
            );
        }

        return $next($request);
    }
}
