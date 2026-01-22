<?php

declare(strict_types=1);

namespace Marko\Core\Event;

/**
 * Value object representing a discovered observer.
 */
readonly class ObserverDefinition
{
    public function __construct(
        public string $observerClass,
        public string $eventClass,
        public int $priority = 0,
        public bool $async = false,
    ) {}
}
