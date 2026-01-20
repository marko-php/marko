<?php

declare(strict_types=1);

namespace Marko\Core\Event;

/**
 * Stores observers indexed by event class.
 */
class ObserverRegistry
{
    /** @var array<string, array<ObserverDefinition>> */
    private array $observers = [];

    public function register(
        ObserverDefinition $definition,
    ): void {
        $this->observers[$definition->eventClass][] = $definition;
    }

    /**
     * Get all observers for an event class.
     *
     * @return array<ObserverDefinition>
     */
    public function getObserversFor(
        string $eventClass,
    ): array {
        return $this->observers[$eventClass] ?? [];
    }
}
