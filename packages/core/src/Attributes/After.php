<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class After
{
    public function __construct(
        public int $sortOrder = 0,
    ) {}
}
