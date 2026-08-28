<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Closure;
use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteMatcherInterface;

/**
 * A {@see RouteMatcherInterface} whose behavior per call is fully
 * controlled by an injected callback, with every call counted — lets
 * {@see WorkerRequestHandler} tests exercise the routing seam without a
 * booted application.
 */
class FakeRouteMatcher implements RouteMatcherInterface
{
    public private(set) int $matchCount = 0;

    public function __construct(
        private readonly ?Closure $onMatch = null,
    ) {}

    public function match(
        string $method,
        string $path,
    ): ?MatchedRoute {
        $this->matchCount++;

        return $this->onMatch !== null ? ($this->onMatch)($method, $path) : null;
    }
}
