<?php

declare(strict_types=1);

namespace Marko\Authorization\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Can
{
    public function __construct(
        public string $ability,
        public ?string $entityClass = null,
    ) {}
}
