<?php

declare(strict_types=1);

namespace Marko\Queue;

class AsyncObserverJob extends Job
{
    public function __construct(
        public readonly string $observerClass,
        public readonly string $eventData,
    ) {}

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
