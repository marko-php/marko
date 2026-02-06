<?php

declare(strict_types=1);

namespace Marko\Admin\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class AdminPermission
{
    public function __construct(
        public string $id,
        public string $label = '',
    ) {}
}
