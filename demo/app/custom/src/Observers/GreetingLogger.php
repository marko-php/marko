<?php

declare(strict_types=1);

namespace Demo\Custom\Observers;

use Demo\Greeter\Events\GreetingCreated;
use Marko\Core\Attributes\Observer;

#[Observer(event: GreetingCreated::class, priority: 100)]
class GreetingLogger
{
    /** @var array<string> */
    public static array $logs = [];

    public function handle(GreetingCreated $event): void
    {
        self::$logs[] = "Greeting created for {$event->name}: {$event->greeting}";
    }
}
