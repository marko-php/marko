<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Core\Exceptions\HttpExceptionInterface;

/**
 * Turns some HTTP exceptions into a response of its own instead of the
 * ExceptionRenderer's default error page.
 *
 * Mark the implementation with #[HttpExceptionResponder]. The renderer asks
 * every responder in priority order; the first non-null Response is returned
 * and still flows back out through every middleware.
 */
interface HttpExceptionResponderInterface
{
    /**
     * A response for the exception, or null to let the next responder (and
     * finally the default rendering) handle it.
     */
    public function respond(
        HttpExceptionInterface $exception,
        Request $request,
    ): ?Response;
}
