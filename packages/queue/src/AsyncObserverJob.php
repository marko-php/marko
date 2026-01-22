<?php

declare(strict_types=1);

namespace Marko\Queue;

class AsyncObserverJob extends Job
{
    public function __construct(
        private string $observerClass,
        private string $eventData,
    ) {}

    public function getObserverClass(): string
    {
        return $this->observerClass;
    }

    public function getEventData(): string
    {
        return $this->eventData;
    }

    public function handle(
        ?callable $resolver = null,
    ): void {
        $event = unserialize($this->eventData);

        if ($resolver !== null) {
            $observer = $resolver($this->observerClass);
            $observer->handle($event);
        }
        // When no resolver provided, this is a no-op placeholder
        // Real implementation will use container to resolve observer
    }
}
