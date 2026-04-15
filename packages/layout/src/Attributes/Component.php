<?php

declare(strict_types=1);

namespace Marko\Layout\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Component
{
    public function __construct(
        public string $template,
        public string|null $slot = null,
        public string|array $handle = 'default',
        public array $slots = [],
        public int $sortOrder = 0,
        public string|null $before = null,
        public string|null $after = null,
    ) {}
}
