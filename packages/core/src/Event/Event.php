<?php

declare(strict_types=1);

namespace Marko\Core\Event;

abstract class Event
{
    public private(set) bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
