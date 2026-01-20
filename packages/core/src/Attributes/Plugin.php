<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Plugin
{
    public function __construct(
        public string $target,
    ) {}
}
