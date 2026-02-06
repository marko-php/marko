<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Config;

interface AdminAuthConfigInterface
{
    public function getGuardName(): string;

    public function getSuperAdminRoleSlug(): string;
}
