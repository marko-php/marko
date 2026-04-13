<?php

declare(strict_types=1);

namespace Marko\Inertia\React\Contracts;

use Marko\Vite\ValueObjects\FilePublishResult;

interface InertiaReactPublisherInterface
{
    public function publishJsEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult;
}
