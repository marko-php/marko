<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Events\AdminUserCreated;
use Marko\AdminAuth\Events\AdminUserUpdated;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;

/**
 * @extends Repository<AdminUser>
 */
class AdminUserRepository extends Repository implements AdminUserRepositoryInterface
{
    protected const string ENTITY_CLASS = AdminUser::class;

    /**
     * Find an admin user by email address.
     */
    public function findByEmail(
        string $email,
    ): ?AdminUser {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Get all roles for a user.
     *
     * @return array<Role>
     * @throws EntityException
     */
    public function getRolesForUser(
        int $userId,
    ): array {
        $sql = 'SELECT r.* FROM roles r
            INNER JOIN admin_user_roles aur ON r.id = aur.role_id
            WHERE aur.user_id = ?';

        $rows = $this->connection->query($sql, [$userId]);

        $roleMetadata = $this->metadataFactory->parse(Role::class);

        return array_map(
            fn (array $row): Role => $this->hydrator->hydrate(
                Role::class,
                $row,
                $roleMetadata,
            ),
            $rows,
        );
    }

    /**
     * Sync roles for a user, replacing all existing.
     *
     * @param array<int> $roleIds
     */
    public function syncRoles(
        int $userId,
        array $roleIds,
    ): void {
        // Remove all existing roles for this user
        $sql = 'DELETE FROM admin_user_roles WHERE user_id = ?';
        $this->connection->execute($sql, [$userId]);

        // Attach the new roles
        foreach ($roleIds as $roleId) {
            $sql = 'INSERT INTO admin_user_roles (user_id, role_id) VALUES (?, ?)';
            $this->connection->execute($sql, [$userId, $roleId]);
        }
    }

    /**
     * Save an admin user, dispatching appropriate events.
     *
     * @throws RepositoryException
     */
    public function save(
        Entity $entity,
    ): void {
        if (!$entity instanceof AdminUser) {
            parent::save($entity);

            return;
        }

        $isNew = $entity->id === null;

        parent::save($entity);

        $this->dispatchSaveEvent($entity, $isNew);
    }

    private function dispatchSaveEvent(
        AdminUser $user,
        bool $isNew,
    ): void {
        if ($this->eventDispatcher === null) {
            return;
        }

        if ($isNew) {
            $this->eventDispatcher->dispatch(new AdminUserCreated(
                user: $user,
            ));
        } else {
            $this->eventDispatcher->dispatch(new AdminUserUpdated(
                user: $user,
            ));
        }
    }
}
