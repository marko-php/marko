<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Preference
{
    public function __construct(
        public string $replaces,
    ) {}
}
