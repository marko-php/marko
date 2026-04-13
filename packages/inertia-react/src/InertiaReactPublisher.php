<?php

declare(strict_types=1);

namespace Marko\Inertia\React;

use Marko\Inertia\React\Contracts\InertiaReactPublisherInterface;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ValueObjects\ViteConfig;

class InertiaReactPublisher implements InertiaReactPublisherInterface
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
            $this->renderer->renderInertiaReactEntrypoint(),
            $force,
            $dryRun,
        );
    }
}
