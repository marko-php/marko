<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Marko\Config\ConfigRepositoryInterface;

readonly class TwigViewConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function cacheDirectory(): string
    {
        return $this->config->getString('view.cache_directory');
    }

    public function extension(): string
    {
        return $this->config->getString('view.extension');
    }

    public function autoRefresh(): bool
    {
        return $this->config->getBool('view.auto_refresh');
    }

    public function strictVariables(): bool
    {
        return $this->config->getBool('view.strict_variables');
    }

    public function autoescape(): string
    {
        return $this->config->getString('view.autoescape');
    }

    public function debug(): bool
    {
        return $this->config->getBool('view.debug');
    }

    public function charset(): string
    {
        return $this->config->getString('view.charset');
    }
}
