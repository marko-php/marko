<?php

declare(strict_types=1);

namespace Marko\Routing;

readonly class MatchedRoute
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public RouteDefinition $route,
        public array $parameters = [],
    ) {}
}
