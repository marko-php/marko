<?php

declare(strict_types=1);

namespace Marko\Core\Event;

interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered observers.
     */
    public function dispatch(Event $event): void;
}
