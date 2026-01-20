<?php

declare(strict_types=1);

namespace Marko\Routing\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

/**
 * Contract for middleware classes.
 *
 * Middleware can intercept requests before they reach the controller
 * and/or modify responses before they are sent to the client.
 */
interface MiddlewareInterface
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request The incoming HTTP request
     * @param callable(Request): Response $next The next middleware or handler in the pipeline
     * @return Response The HTTP response
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response;
}
