<?php

declare(strict_types=1);

namespace Marko\Security\Middleware;

/**
 * The CSRF check at a position the route chooses. The global CsrfMiddleware
 * runs after a route's own middleware; a route whose middleware must only
 * see verified requests excludes it and lists this one first:
 *
 *     #[Middleware([RouteCsrfMiddleware::class, RateLimitMiddleware::class])]
 *     #[WithoutMiddleware(CsrfMiddleware::class)]
 *
 * #[WithoutMiddleware] removes middleware by exact class, so this subclass stays.
 */
class RouteCsrfMiddleware extends CsrfMiddleware {}
