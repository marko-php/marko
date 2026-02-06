<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

interface RolePermissionInterface
{
    public function getRoleId(): int;

    public function getPermissionId(): int;
}
