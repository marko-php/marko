<?php

declare(strict_types=1);

namespace Marko\Vite\Contracts;

use Marko\Vite\ValueObjects\FilePublishResult;

interface VitePublisherInterface
{
    public function publishConfig(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult;

    public function publishJsEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult;
}
