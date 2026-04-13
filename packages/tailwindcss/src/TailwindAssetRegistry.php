<?php

declare(strict_types=1);

namespace Marko\TailwindCss;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\TailwindCss\Events\TailwindAssetsRegistering;

class TailwindAssetRegistry
{
    public function __construct(
        private readonly EventDispatcherInterface $events,
    ) {}

    /**
     * @return array<string>
     */
    public function contentPaths(): array
    {
        return $this->collect()->contentPaths;
    }

    /**
     * @return array<string>
     */
    public function entrypoints(): array
    {
        return $this->collect()->entrypoints;
    }

    protected function collect(): TailwindAssetsRegistering
    {
        $event = new TailwindAssetsRegistering();
        $this->events->dispatch($event);

        $event->contentPaths = array_values(array_unique($event->contentPaths));
        $event->entrypoints = array_values(array_unique($event->entrypoints));

        return $event;
    }
}
