<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\MiddlewareDecoration;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Deliberately violates the decoration rule: constructs a brand-new
 * Response after already calling $next(), instead of decorating the
 * response $next() returned. The constructor arguments here are literals,
 * not sourced from the earlier response at all — this fixture proves the
 * architecture test catches the rebuild by position, not by argument
 * provenance.
 */
class RebuildAfterNextMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $next($request);

        return new Response(
            body: 'rebuilt',
            statusCode: 200,
            headers: ['X-Custom' => 'value'],
        );
    }
}
