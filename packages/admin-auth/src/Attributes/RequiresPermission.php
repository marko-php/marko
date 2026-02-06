<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class RequiresPermission
{
    public function __construct(
        public string $permission,
    ) {}
}
