<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Discovery;

use Marko\Admin\Discovery\AdminSectionDiscovery;
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;

class PermissionDiscovery
{
    public function __construct(
        private readonly PermissionRegistryInterface $registry,
        private readonly AdminSectionDiscovery $sectionDiscovery,
    ) {}

    /**
     * Discover permissions from AdminPermission attributes on an AdminSection class.
     *
     * @param class-string $className
     */
    public function discoverFromClass(
        string $className,
    ): void {
        $definition = $this->sectionDiscovery->parseAdminSectionClass($className);

        foreach ($definition->permissions as $permission) {
            $group = $this->deriveGroup($permission->id);

            $this->registry->register(
                key: $permission->id,
                label: $permission->label,
                group: $group,
            );
        }
    }

    private function deriveGroup(
        string $key,
    ): string {
        $parts = explode('.', $key);

        return $parts[0];
    }
}
