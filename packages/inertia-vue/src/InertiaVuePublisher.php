<?php

declare(strict_types=1);

namespace Marko\Inertia\Vue;

use Marko\Inertia\Vue\Contracts\InertiaVuePublisherInterface;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ValueObjects\ViteConfig;

class InertiaVuePublisher implements InertiaVuePublisherInterface
{
    public function __construct(
        private readonly ViteConfig $viteConfig,
        private readonly ProjectFilePublisher $publisher,
        private readonly ScaffoldTemplateRenderer $renderer,
    ) {}

    public function publishJsEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        return $this->publisher->publish(
            $this->viteConfig->rootEntrypointPath,
            $this->renderer->renderInertiaVueEntrypoint(),
            $force,
            $dryRun,
        );
    }
}
