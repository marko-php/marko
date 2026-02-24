<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Testing\Exceptions\AssertionFailedException;

class FakeEventDispatcher implements EventDispatcherInterface
{
    /** @var array<Event> */
    public private(set) array $dispatched = [];

    public function dispatch(
        Event $event,
    ): void {
        $this->dispatched[] = $event;
    }

    /** @return array<Event> */
    public function dispatched(
        string $eventClass,
    ): array {
        return array_values(
            array_filter(
                $this->dispatched,
                fn (Event $event) => $event instanceof $eventClass,
            ),
        );
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertDispatched(
        string $eventClass,
    ): void {
        if (count($this->dispatched($eventClass)) === 0) {
            throw AssertionFailedException::expectedDispatched($eventClass);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNotDispatched(
        string $eventClass,
    ): void {
        if (count($this->dispatched($eventClass)) > 0) {
            throw AssertionFailedException::unexpectedDispatched($eventClass);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertDispatchedCount(
        string $eventClass,
        int $expected,
    ): void {
        $actual = count($this->dispatched($eventClass));

        if ($actual !== $expected) {
            throw AssertionFailedException::expectedCount('dispatched ' . $eventClass, $expected, $actual);
        }
    }

    public function clear(): void
    {
        $this->dispatched = [];
    }
}
