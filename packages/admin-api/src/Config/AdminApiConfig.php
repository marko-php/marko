<?php

declare(strict_types=1);

namespace Marko\AdminApi\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class AdminApiConfig implements AdminApiConfigInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function getVersion(): string
    {
        return $this->config->getString('admin-api.version');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function getRateLimit(): int
    {
        return $this->config->getInt('admin-api.rate_limit');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function getGuardName(): string
    {
        return $this->config->getString('admin-api.guard');
    }
}
