<?php

declare(strict_types=1);

namespace Marko\AdminApi\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class AdminApiConfig implements AdminApiConfigInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function getVersion(): string
    {
        return $this->config->getString('admin-api.version');
    }

    public function getRateLimit(): int
    {
        return $this->config->getInt('admin-api.rate_limit');
    }

    public function getGuardName(): string
    {
        return $this->config->getString('admin-api.guard');
    }
}
