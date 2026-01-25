<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Integration;

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;

/**
 * Test event dispatcher that collects dispatched events.
 */
class TestEventDispatcher implements EventDispatcherInterface
{
    /** @var array<Event> */
    public array $events = [];

    public function dispatch(
        Event $event,
    ): void {
        $this->events[] = $event;
    }

    public function clear(): void
    {
        $this->events = [];
    }
}
