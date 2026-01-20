<?php

declare(strict_types=1);

namespace Demo\Greeter\Events;

use Marko\Core\Event\Event;

class GreetingCreated extends Event
{
    public function __construct(
        public readonly string $greeting,
        public readonly string $name,
    ) {}
}
