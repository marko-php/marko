<?php

declare(strict_types=1);

namespace Marko\Inertia\Vue\Contracts;

use Marko\Vite\ValueObjects\FilePublishResult;

interface InertiaVuePublisherInterface
{
    public function publishJsEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult;
}
