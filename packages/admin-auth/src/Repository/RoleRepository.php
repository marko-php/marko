<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Closure;
use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Events\RoleCreated;
use Marko\AdminAuth\Events\RoleDeleted;
use Marko\AdminAuth\Events\RoleUpdated;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;

class RoleRepository extends Repository implements RoleRepositoryInterface
{
    protected const string ENTITY_CLASS = Role::class;

    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $metadataFactory,
        EntityHydrator $hydrator,
        ?Closure $queryBuilderFactory = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory);
    }

    /**
     * Save a role, dispatching appropriate events.
     */
    public function save(
        Entity $entity,
    ): void {
        if (!$entity instanceof Role) {
            parent::save($entity);

            return;
        }

        $isNew = $entity->id === null;

        parent::save($entity);

        $this->dispatchSaveEvent($entity, $isNew);
    }

    /**
     * Delete a role, dispatching appropriate events.
     */
    public function delete(
        Entity $entity,
    ): void {
        if (!$entity instanceof Role) {
            parent::delete($entity);

            return;
        }

        parent::delete($entity);

        $this->eventDispatcher?->dispatch(new RoleDeleted(
            role: $entity,
        ));
    }

    private function dispatchSaveEvent(
        Role $role,
        bool $isNew,
    ): void {
        if ($this->eventDispatcher === null) {
            return;
        }

        if ($isNew) {
            $this->eventDispatcher->dispatch(new RoleCreated(
                role: $role,
            ));
        } else {
            $this->eventDispatcher->dispatch(new RoleUpdated(
                role: $role,
            ));
        }
    }

    /**
     * Find a role by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Role {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Get all permissions for a role.
     *
     * @return array<Permission>
     */
    public function getPermissionsForRole(
        int $roleId,
    ): array {
        $sql = 'SELECT p.* FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?';

        $rows = $this->connection->query($sql, [$roleId]);

        $permissionMetadata = $this->metadataFactory->parse(Permission::class);

        return array_map(
            fn (array $row): Permission => $this->hydrator->hydrate(
                Permission::class,
                $row,
                $permissionMetadata,
            ),
            $rows,
        );
    }

    /**
     * Sync permissions for a role, replacing all existing.
     *
     * @param array<int> $permissionIds
     */
    public function syncPermissions(
        int $roleId,
        array $permissionIds,
    ): void {
        // Remove all existing permissions for this role
        $sql = 'DELETE FROM role_permissions WHERE role_id = ?';
        $this->connection->execute($sql, [$roleId]);

        // Attach the new permissions
        foreach ($permissionIds as $permissionId) {
            $sql = 'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)';
            $this->connection->execute($sql, [$roleId, $permissionId]);
        }
    }

    /**
     * Check if a slug is unique within the roles table.
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        $sql = sprintf(
            'SELECT * FROM %s WHERE slug = ?',
            $this->metadata->tableName,
        );
        $bindings = [$slug];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $bindings[] = $excludeId;
        }

        $rows = $this->connection->query($sql, $bindings);

        return count($rows) === 0;
    }
}
