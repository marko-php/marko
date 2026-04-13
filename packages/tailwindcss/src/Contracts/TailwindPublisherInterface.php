<?php

declare(strict_types=1);

namespace Marko\TailwindCss\Contracts;

use Marko\Vite\ValueObjects\FilePublishResult;

interface TailwindPublisherInterface
{
    public function publishCssEntrypoint(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult;
}
