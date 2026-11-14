<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Exceptions\AdminAuthException;
use Marko\AdminAuth\IdentifierFormat;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\BatchInsertException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Throwable;

/**
 * @extends Repository<Permission>
 */
class PermissionRepository extends Repository implements PermissionRepositoryInterface
{
    protected const string ENTITY_CLASS = Permission::class;

    private const int ROWS_PER_CHUNK = 500;

    /**
     * Save a permission whose key matches IdentifierFormat::PERMISSION_KEY_PATTERN.
     *
     * @throws AdminAuthException|RepositoryException
     */
    public function save(
        Entity $entity,
    ): void {
        if ($entity instanceof Permission) {
            $this->assertCanonicalKey($entity);
        }

        parent::save($entity);
    }

    /**
     * Insert permissions whose keys all match IdentifierFormat::PERMISSION_KEY_PATTERN; nothing is inserted otherwise.
     *
     * @param array<Entity> $entities
     * @throws AdminAuthException|BatchInsertException|RepositoryException|Throwable
     */
    public function insertBatch(
        array $entities,
    ): void {
        foreach ($entities as $entity) {
            if ($entity instanceof Permission) {
                $this->assertCanonicalKey($entity);
            }
        }

        parent::insertBatch($entities);
    }

    /**
     * Find a permission by its key.
     *
     * A key outside IdentifierFormat::PERMISSION_KEY_PATTERN can't be saved, so it returns null without a query (on
     * MySQL/MariaDB the collation would otherwise match "Posts.Edit" to "posts.edit").
     */
    public function findByKey(
        string $key,
    ): ?Permission {
        if (!IdentifierFormat::isPermissionKey($key)) {
            return null;
        }

        return $this->findOneBy(['key' => $key]);
    }

    /**
     * Find all permissions in a group.
     *
     * The group column is a reserved word; findBy() quotes it through the connection, so this works on every
     * driver.
     *
     * @return array<Permission>
     */
    public function findByGroup(
        string $group,
    ): array {
        return $this->findBy(['group' => $group])->toArray();
    }

    /**
     * Sync permissions from the registry to the database.
     *
     * Inserts registered permissions missing from the table and updates the label and group of existing rows
     * that changed, in one transaction. Reports the rows whose key is no longer registered (with the number of
     * roles holding each) and the wildcard keys, but deletes nothing.
     *
     * @throws Throwable
     */
    public function syncFromRegistry(
        PermissionRegistryInterface $registry,
    ): PermissionSyncResult {
        return $this->inTransaction(function () use ($registry): PermissionSyncResult {
            $permissions = $this->permissionsByKey();
            $created = [];
            $updated = [];

            foreach ($registry->all() as $registered) {
                $permission = $permissions[$registered->key] ?? null;

                if ($permission === null) {
                    $permission = $this->caseVariantOf($registered->key, $permissions);
                }

                if (
                    $permission !== null
                    && $permission->key !== $registered->key
                ) {
                    unset($permissions[$permission->key]);
                    $permission->key = $registered->key;
                    $permission->label = $registered->label;
                    $permission->group = $registered->group;
                    $permissions[$registered->key] = $permission;

                    $this->save($permission);
                    $updated[] = $registered->key;

                    continue;
                }

                if ($permission === null) {
                    $permission = new Permission();
                    $permission->key = $registered->key;
                    $permission->label = $registered->label;
                    $permission->group = $registered->group;

                    $this->save($permission);
                    $created[] = $registered->key;

                    continue;
                }

                if (
                    $permission->label === $registered->label
                    && $permission->group === $registered->group
                ) {
                    continue;
                }

                $permission->label = $registered->label;
                $permission->group = $registered->group;

                $this->save($permission);
                $updated[] = $registered->key;
            }

            $wildcardKeys = array_values(array_filter(
                array_map(fn (Permission $permission): string => $permission->key, array_values($permissions)),
                fn (string $key): bool => $this->isWildcard($key),
            ));
            sort($wildcardKeys, SORT_STRING);

            return new PermissionSyncResult(
                registeredCount: count($registry->all()),
                created: $created,
                updated: $updated,
                unregistered: $this->unregisteredAmong($permissions, $registry),
                wildcardKeys: $wildcardKeys,
            );
        });
    }

    /**
     * Find the permissions whose key is no longer registered, sorted by key.
     *
     * Keys containing "*" are wildcard grants, never registered by #[AdminPermission], so they are never
     * included.
     *
     * @return list<UnregisteredPermission>
     */
    public function findUnregistered(
        PermissionRegistryInterface $registry,
    ): array {
        return $this->unregisteredAmong($this->permissionsByKey(), $registry);
    }

    /**
     * Delete the permissions whose key is no longer registered, with their role assignments.
     *
     * The role_permissions rows are deleted explicitly before the permissions rows, in one transaction, so the
     * result does not depend on the foreign key's ON DELETE CASCADE. Keys containing "*" are never deleted. The
     * rows are deleted with SQL, so no EntityDeleting/EntityDeleted events are dispatched for them.
     *
     * @return list<UnregisteredPermission> The permissions removed, with the number of roles that held each
     * @throws Throwable
     */
    public function pruneUnregistered(
        PermissionRegistryInterface $registry,
    ): array {
        return $this->inTransaction(function () use ($registry): array {
            $unregistered = $this->findUnregistered($registry);
            $ids = array_map(fn (UnregisteredPermission $permission): int => $permission->id, $unregistered);

            foreach (array_chunk($ids, self::ROWS_PER_CHUNK) as $chunk) {
                $placeholders = $this->placeholders($chunk);

                $this->connection->execute(
                    sprintf(
                        'DELETE FROM %s WHERE %s IN (%s)',
                        $this->connection->quoteIdentifier('role_permissions'),
                        $this->connection->quoteIdentifier('permission_id'),
                        $placeholders,
                    ),
                    $chunk,
                );
                $this->connection->execute(
                    sprintf(
                        'DELETE FROM %s WHERE %s IN (%s)',
                        $this->connection->quoteIdentifier('permissions'),
                        $this->connection->quoteIdentifier('id'),
                        $placeholders,
                    ),
                    $chunk,
                );
            }

            return $unregistered;
        });
    }

    /**
     * @return array<string, Permission> Every permission row, keyed by key
     */
    private function permissionsByKey(): array
    {
        $permissions = [];

        foreach ($this->findAll() as $permission) {
            $permissions[$permission->key] = $permission;
        }

        return $permissions;
    }

    /**
     * The stored row, with the lowest id, whose key differs from the registered key only in letter case.
     *
     * Such a row predates the lowercase key format. Inserting the registered key next to it would fail on the
     * MySQL/MariaDB unique index (their default collations ignore case) and add a second row on PostgreSQL, so
     * sync renames it instead, keeping its id and role assignments, and every driver ends with the same table.
     *
     * @param array<string, Permission> $permissions
     */
    private function caseVariantOf(
        string $registeredKey,
        array $permissions,
    ): ?Permission {
        $variants = array_filter(
            $permissions,
            fn (Permission $permission): bool => $permission->key !== $registeredKey
                && strtolower($permission->key) === $registeredKey,
        );
        usort($variants, fn (Permission $a, Permission $b): int => (int) $a->id <=> (int) $b->id);

        return $variants[0] ?? null;
    }

    /**
     * @throws AdminAuthException
     */
    private function assertCanonicalKey(
        Permission $permission,
    ): void {
        if (!IdentifierFormat::isPermissionKey($permission->key)) {
            throw AdminAuthException::invalidPermissionKey($permission->key);
        }
    }

    /**
     * @param array<string, Permission> $permissions
     * @return list<UnregisteredPermission>
     */
    private function unregisteredAmong(
        array $permissions,
        PermissionRegistryInterface $registry,
    ): array {
        $registeredKeys = array_flip(
            array_map(fn (mixed $registered): string => $registered->key, $registry->all()),
        );

        $unregistered = array_filter(
            $permissions,
            fn (Permission $permission): bool => !isset($registeredKeys[$permission->key])
                && !$this->isWildcard($permission->key),
        );
        ksort($unregistered, SORT_STRING);

        $roleCounts = $this->roleCounts(
            array_map(fn (Permission $permission): int => (int) $permission->id, array_values($unregistered)),
        );

        return array_map(
            fn (Permission $permission): UnregisteredPermission => new UnregisteredPermission(
                id: (int) $permission->id,
                key: $permission->key,
                label: $permission->label,
                group: $permission->group,
                roleCount: $roleCounts[(int) $permission->id] ?? 0,
            ),
            array_values($unregistered),
        );
    }

    /**
     * The number of distinct roles holding each permission.
     *
     * @param list<int> $permissionIds
     * @return array<int, int> Role counts keyed by permission id; permissions no role holds are absent
     */
    private function roleCounts(
        array $permissionIds,
    ): array {
        $counts = [];

        foreach (array_chunk($permissionIds, self::ROWS_PER_CHUNK) as $chunk) {
            $rows = $this->connection->query(
                sprintf(
                    'SELECT %1$s, COUNT(DISTINCT %2$s) AS role_count FROM %3$s WHERE %1$s IN (%4$s) GROUP BY %1$s',
                    $this->connection->quoteIdentifier('permission_id'),
                    $this->connection->quoteIdentifier('role_id'),
                    $this->connection->quoteIdentifier('role_permissions'),
                    $this->placeholders($chunk),
                ),
                $chunk,
            );

            foreach ($rows as $row) {
                $counts[(int) $row['permission_id']] = (int) $row['role_count'];
            }
        }

        return $counts;
    }

    private function isWildcard(
        string $key,
    ): bool {
        return str_contains($key, '*');
    }

    /**
     * @param list<int> $values
     */
    private function placeholders(
        array $values,
    ): string {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    /**
     * Run the callback in transaction(), which nests as a savepoint inside a caller's transaction. A connection
     * without transactions (only test doubles; both drivers have them) runs it directly, with no atomicity.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws Throwable
     */
    private function inTransaction(
        callable $callback,
    ): mixed {
        if ($this->connection instanceof TransactionInterface) {
            return $this->connection->transaction($callback);
        }

        return $callback();
    }
}
