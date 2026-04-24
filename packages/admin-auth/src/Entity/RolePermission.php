<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('role_permissions')]
#[Index('idx_role_permissions_unique', ['role_id', 'permission_id'], unique: true)]
class RolePermission extends Entity implements RolePermissionInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(references: 'roles.id', onDelete: 'CASCADE')]
    public int $roleId;

    #[Column(references: 'permissions.id', onDelete: 'CASCADE')]
    public int $permissionId;

    public function getRoleId(): int
    {
        return $this->roleId;
    }

    public function getPermissionId(): int
    {
        return $this->permissionId;
    }
}
