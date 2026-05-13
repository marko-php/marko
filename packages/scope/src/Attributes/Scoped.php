<?php

declare(strict_types=1);

namespace Marko\Scope\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Scoped
{
    /**
     * @param list<string> $axes
     */
    public function __construct(
        public array $axes = [],
    ) {}
}
