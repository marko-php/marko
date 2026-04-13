<?php

declare(strict_types=1);

namespace Marko\TailwindCss;

use Marko\TailwindCss\Contracts\TailwindEntrypointProviderInterface;
use Marko\TailwindCss\Contracts\TailwindPublisherInterface;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\ValueObjects\FilePublishResult;

class TailwindPublisher implements TailwindPublisherInterface
{
    public function __construct(
        private readonly TailwindEntrypointProviderInterface $entrypointProvider,
        private readonly ProjectFilePublisher $publisher,
    ) {}

    public function publishCssEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult
    {
        $entrypoint = $this->entrypointProvider->entrypoints()[0] ?? 'resources/css/app.css';

        return $this->publisher->publish(
            $entrypoint,
            (string) file_get_contents(dirname(__DIR__) . '/stubs/resources/css/app.css'),
            $force,
            $dryRun,
        );
    }
}
