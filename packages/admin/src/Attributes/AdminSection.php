<?php

declare(strict_types=1);

namespace Marko\Admin\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class AdminSection
{
    public function __construct(
        public string $id,
        public string $label,
        public string $icon = '',
        public int $sortOrder = 0,
    ) {}
}
