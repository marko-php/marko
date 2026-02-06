<?php

declare(strict_types=1);

namespace Marko\Admin\Config;

use Marko\Admin\Exceptions\InvalidAdminConfigException;
use Marko\Config\ConfigRepositoryInterface;

readonly class AdminConfig implements AdminConfigInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function getRoutePrefix(): string
    {
        $prefix = $this->config->getString('admin.route_prefix');

        if (!str_starts_with($prefix, '/')) {
            throw InvalidAdminConfigException::routePrefixMustStartWithSlash($prefix);
        }

        return $prefix;
    }

    public function getName(): string
    {
        return $this->config->getString('admin.name');
    }
}
