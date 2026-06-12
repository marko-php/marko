<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Events\RoleCreated;
use Marko\AdminAuth\Events\RoleDeleted;
use Marko\AdminAuth\Events\RoleUpdated;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Throwable;

/**
 * @extends Repository<Role>
 */
class RoleRepository extends Repository implements RoleRepositoryInterface
{
    protected const string ENTITY_CLASS = Role::class;

    private const int SYNC_ROWS_PER_CHUNK = 500;

    /**
     * Save a role, dispatching appropriate events.
     *
     * @throws RepositoryException
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
     *
     * @throws RepositoryException
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
     * @throws EntityException
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
     * Get the deduplicated permission set across all given role ids in a single query.
     *
     * @param array<int> $roleIds
     * @return array<Permission>
     * @throws EntityException
     */
    public function getPermissionsForRoles(
        array $roleIds,
    ): array {
        if ($roleIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($roleIds), '?'));
        $sql = "SELECT DISTINCT p.* FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id IN ($placeholders)";

        $rows = $this->connection->query($sql, $roleIds);

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
     * Wraps the DELETE and batched INSERT in a transaction when the connection
     * supports it, so a mid-sync failure cannot leave a role half-synced.
     *
     * @param array<int> $permissionIds
     * @throws Throwable
     */
    public function syncPermissions(
        int $roleId,
        array $permissionIds,
    ): void {
        $ownsTransaction = $this->connection instanceof TransactionInterface
            && !$this->connection->inTransaction();

        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $this->connection->execute(
                'DELETE FROM role_permissions WHERE role_id = ?',
                [$roleId],
            );

            foreach (array_chunk($permissionIds, self::SYNC_ROWS_PER_CHUNK) as $chunk) {
                $placeholders = implode(
                    ', ',
                    array_fill(0, count($chunk), '(?, ?)'),
                );
                $bindings = [];
                foreach ($chunk as $permissionId) {
                    $bindings[] = $roleId;
                    $bindings[] = $permissionId;
                }
                $this->connection->execute(
                    "INSERT INTO role_permissions (role_id, permission_id) VALUES $placeholders",
                    $bindings,
                );
            }

            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction) {
                $this->connection->rollback();
            }

            throw $e;
        }
    }

    /**
     * Check if a slug is unique within the roles table.
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        return $this->isColumnUnique('slug', $slug, $excludeId);
    }
}
