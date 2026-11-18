<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * The roles assigned to an admin user. db:migrate creates the admin_user_roles table from this entity;
 * AdminUserRepository::syncRoles() and getRolesForUser() read and write it.
 */
#[Table('admin_user_roles')]
#[Index(['user_id', 'role_id'], unique: true)]
#[Index(['role_id'])]
class AdminUserRole extends Entity implements AdminUserRoleInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(references: 'admin_users.id', onDelete: 'CASCADE')]
    public int $userId;

    #[Column(references: 'roles.id', onDelete: 'CASCADE')]
    public int $roleId;

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getRoleId(): int
    {
        return $this->roleId;
    }
}
