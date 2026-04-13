<?php

declare(strict_types=1);

namespace Marko\TailwindCss\Events;

use Marko\Core\Event\Event;

class TailwindAssetsRegistering extends Event
{
    /**
     * @param array<string> $contentPaths
     * @param array<string> $entrypoints
     */
    public function __construct(
        public array $contentPaths = [],
        public array $entrypoints = [],
    ) {}

    public function registerContentPath(string $path): void
    {
        if (!in_array($path, $this->contentPaths, true)) {
            $this->contentPaths[] = $path;
        }
    }

    public function registerEntrypoint(string $entrypoint): void
    {
        if (!in_array($entrypoint, $this->entrypoints, true)) {
            $this->entrypoints[] = $entrypoint;
        }
    }
}
