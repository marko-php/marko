<?php

declare(strict_types=1);

namespace Marko\TailwindCss\Observer;

use Marko\Core\Attributes\Observer;
use Marko\TailwindCss\ContentPathCollector;
use Marko\TailwindCss\Contracts\TailwindEntrypointProviderInterface;
use Marko\TailwindCss\Events\TailwindAssetsRegistering;

#[Observer(TailwindAssetsRegistering::class)]
class RegisterTailwindAssets
{
    public function __construct(
        private readonly ContentPathCollector $contentPathCollector,
        private readonly TailwindEntrypointProviderInterface $entrypointProvider,
    ) {}

    public function handle(TailwindAssetsRegistering $event): void
    {
        foreach ($this->contentPathCollector->collect() as $path) {
            $event->registerContentPath($path);
        }

        foreach ($this->entrypointProvider->entrypoints() as $entrypoint) {
            $event->registerEntrypoint($entrypoint);
        }
    }
}
