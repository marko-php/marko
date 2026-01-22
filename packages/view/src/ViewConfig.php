<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\Config\ConfigRepositoryInterface;

readonly class ViewConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function cacheDirectory(): string
    {
        return $this->config->getString('view.cache_directory', '/tmp/views');
    }

    public function extension(): string
    {
        return $this->config->getString('view.extension', '.latte');
    }

    public function autoRefresh(): bool
    {
        return $this->config->getBool('view.auto_refresh', true);
    }

    public function strictTypes(): bool
    {
        return $this->config->getBool('view.strict_types', true);
    }
}
