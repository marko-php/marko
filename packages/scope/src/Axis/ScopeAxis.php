<?php

declare(strict_types=1);

namespace Marko\Scope\Axis;

use Marko\Scope\Hierarchy\ScopeHierarchy;

readonly class ScopeAxis
{
    public function __construct(
        public string $name,
        public ScopeHierarchy $hierarchy,
    ) {}
}
