<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Observer
{
    public function __construct(
        public string $event,
        public int $priority = 0,
        public bool $async = false,
    ) {}
}
