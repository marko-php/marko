<?php

declare(strict_types=1);

namespace Marko\Admin\Discovery;

readonly class AdminPermissionDefinition
{
    public function __construct(
        public string $id,
        public string $label,
    ) {}
}
