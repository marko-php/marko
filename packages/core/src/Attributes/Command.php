<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Command
{
    public function __construct(
        public string $name,
        public string $description = '',
        public array $aliases = [],
    ) {}
}
