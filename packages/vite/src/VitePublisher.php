<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Vite\Contracts\VitePublisherInterface;
use Marko\Vite\ValueObjects\ViteConfig;
use Marko\Vite\ValueObjects\FilePublishResult;

class VitePublisher implements VitePublisherInterface
{
    public function __construct(
        private readonly ViteConfig $config,
        private readonly ProjectFilePublisher $publisher,
        private readonly ScaffoldTemplateRenderer $renderer,
    ) {}

    public function publishConfig(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult
    {
        return $this->publisher->publish(
            $this->config->rootViteConfigPath,
            $this->renderer->renderViteConfig(),
            $force,
            $dryRun,
        );
    }

    public function publishJsEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult
    {
        return $this->publisher->publish(
            $this->config->rootEntrypointPath,
            $this->renderer->renderViteEntrypoint(),
            $force,
            $dryRun,
        );
    }
}
