<?php

declare(strict_types=1);

namespace Marko\Core\Event;

abstract class Event
{
    public bool $propagationStopped = false {
        get {
            return $this->propagationStopped;
        }
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
