<?php

declare(strict_types=1);

namespace Marko\Inertia\Svelte\Contracts;

use Marko\Vite\ValueObjects\FilePublishResult;

interface InertiaSveltePublisherInterface
{
    public function publishJsEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult;
}
