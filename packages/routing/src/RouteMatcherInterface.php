<?php

declare(strict_types=1);

namespace Marko\Routing;

interface RouteMatcherInterface
{
    public function match(string $method, string $path): ?MatchedRoute;
}
