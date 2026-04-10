<?php

declare(strict_types=1);

namespace Marko\Layout\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class Layout
{
    public function __construct(
        public string $component,
    ) {}
}
