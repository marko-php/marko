<?php

declare(strict_types=1);

namespace Marko\Admin\Discovery;

use Marko\Admin\Exceptions\AdminException;
use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;
use ReflectionException;

/**
 * Stores every discovered admin section definition in the discovery cache, so
 * a cached boot registers sections and their permissions without scanning the
 * modules or reflecting on the section classes.
 *
 * Each section is stored as its AdminSectionDefinition fields (plain strings,
 * an int and a list of permission id/label pairs), in discovery order.
 */
readonly class AdminSectionCacheContributor implements DiscoveryCacheContributorInterface
{
    public const string KEY = 'admin_sections';

    public function __construct(
        private AdminSectionDiscovery $adminSectionDiscovery,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return array<int, array{className: string, id: string, label: string, icon: string, sortOrder: int, permissions: array<int, array{id: string, label: string}>}>
     *
     * @throws AdminException|ReflectionException
     */
    public function compile(
        array $modules,
    ): array {
        return array_map(
            fn (AdminSectionDefinition $definition): array => [
                'className' => $definition->className,
                'id' => $definition->id,
                'label' => $definition->label,
                'icon' => $definition->icon,
                'sortOrder' => $definition->sortOrder,
                'permissions' => array_map(
                    fn (AdminPermissionDefinition $permission): array => [
                        'id' => $permission->id,
                        'label' => $permission->label,
                    ],
                    $definition->permissions,
                ),
            ],
            $this->adminSectionDiscovery->discoverAll($modules),
        );
    }

    /**
     * Rebuild the section definitions from a cached section.
     *
     * @param array<mixed> $section
     * @return array<int, AdminSectionDefinition>
     *
     * @throws DiscoveryCacheException
     */
    public function hydrate(
        array $section,
    ): array {
        $definitions = [];

        foreach ($section as $index => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformedSection(self::KEY, "admin section $index must be an array");
            }

            $sortOrder = $record['sortOrder'] ?? null;

            if (!is_int($sortOrder)) {
                throw DiscoveryCacheException::malformedSection(
                    self::KEY,
                    "admin section $index.sortOrder must be an int",
                );
            }

            $definitions[] = new AdminSectionDefinition(
                className: $this->stringField($record, $index, 'className'),
                id: $this->stringField($record, $index, 'id'),
                label: $this->stringField($record, $index, 'label'),
                icon: $this->stringField($record, $index, 'icon'),
                sortOrder: $sortOrder,
                permissions: $this->permissionsField($record, $index),
            );
        }

        return $definitions;
    }

    /**
     * @param array<mixed> $record
     *
     * @throws DiscoveryCacheException
     */
    private function stringField(
        array $record,
        int|string $index,
        string $field,
    ): string {
        if (
            !isset($record[$field])
            || !is_string($record[$field])
        ) {
            throw DiscoveryCacheException::malformedSection(
                self::KEY,
                "admin section $index.$field must be a string",
            );
        }

        return $record[$field];
    }

    /**
     * @param array<mixed> $record
     * @return array<int, AdminPermissionDefinition>
     *
     * @throws DiscoveryCacheException
     */
    private function permissionsField(
        array $record,
        int|string $index,
    ): array {
        $permissions = $record['permissions'] ?? null;

        if (
            !is_array($permissions)
            || !array_all(
                $permissions,
                fn (mixed $permission): bool => is_array($permission)
                    && is_string($permission['id'] ?? null)
                    && is_string($permission['label'] ?? null),
            )
        ) {
            throw DiscoveryCacheException::malformedSection(
                self::KEY,
                "admin section $index.permissions must be a list of {id, label} string pairs",
            );
        }

        /** @var array<array{id: string, label: string}> $permissions */
        return array_values(array_map(
            fn (array $permission): AdminPermissionDefinition => new AdminPermissionDefinition(
                id: $permission['id'],
                label: $permission['label'],
            ),
            $permissions,
        ));
    }
}
