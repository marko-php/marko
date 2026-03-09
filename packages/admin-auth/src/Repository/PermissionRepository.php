<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\Permission;
use Marko\Database\Repository\Repository;

/**
 * @extends Repository<Permission>
 */
class PermissionRepository extends Repository implements PermissionRepositoryInterface
{
    protected const string ENTITY_CLASS = Permission::class;

    /**
     * Find a permission by its key.
     */
    public function findByKey(
        string $key,
    ): ?Permission {
        return $this->findOneBy(['key' => $key]);
    }

    /**
     * Find all permissions in a group.
     *
     * @return array<Permission>
     */
    public function findByGroup(
        string $group,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE `group` = ?',
            $this->metadata->tableName,
        );

        $rows = $this->connection->query($sql, [$group]);

        return array_map(
            fn (array $row): Permission => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Sync permissions from the registry to the database.
     *
     * Creates new permissions that exist in the registry but not in the database.
     * Preserves existing permissions.
     */
    public function syncFromRegistry(PermissionRegistryInterface $registry): void
    {
        $registeredPermissions = $registry->all();

        foreach ($registeredPermissions as $registered) {
            $existing = $this->findByKey($registered->key);

            if ($existing !== null) {
                continue;
            }

            $permission = new Permission();
            $permission->key = $registered->key;
            $permission->label = $registered->label;
            $permission->group = $registered->group;

            $this->save($permission);
        }
    }
}
