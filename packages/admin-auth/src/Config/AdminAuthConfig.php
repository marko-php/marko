<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class AdminAuthConfig implements AdminAuthConfigInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function getGuardName(): string
    {
        return $this->config->getString('admin-auth.guard');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function getSuperAdminRoleSlug(): string
    {
        return $this->config->getString('admin-auth.super_admin_role');
    }
}
