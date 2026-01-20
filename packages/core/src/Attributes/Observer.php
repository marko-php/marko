<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Observer
{
    public function __construct(
        public readonly string $event,
        public readonly int $priority = 0,
    ) {}
}
