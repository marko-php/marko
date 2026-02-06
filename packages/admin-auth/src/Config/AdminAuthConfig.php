<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class AdminAuthConfig implements AdminAuthConfigInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function getGuardName(): string
    {
        return $this->config->getString('admin-auth.guard');
    }

    public function getSuperAdminRoleSlug(): string
    {
        return $this->config->getString('admin-auth.super_admin_role');
    }
}
