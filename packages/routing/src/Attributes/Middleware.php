<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class Middleware
{
    /**
     * @var array<class-string>
     */
    public array $middleware;

    /**
     * @param class-string|array<class-string> $middleware
     */
    public function __construct(string|array $middleware)
    {
        $this->middleware = is_array($middleware) ? $middleware : [$middleware];
    }
}
