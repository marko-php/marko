<?php

declare(strict_types=1);

namespace Marko\Admin\Discovery;

readonly class AdminSectionDefinition
{
    /**
     * @param array<AdminPermissionDefinition> $permissions
     */
    public function __construct(
        public string $className,
        public string $id,
        public string $label,
        public string $icon,
        public int $sortOrder,
        public array $permissions = [],
    ) {}
}
