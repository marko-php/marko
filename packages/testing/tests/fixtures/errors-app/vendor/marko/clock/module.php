<?php

declare(strict_types=1);

use Marko\Clock\SystemClock;
use Psr\Clock\ClockInterface;

return [
    'bindings' => [
        ClockInterface::class => SystemClock::class,
    ],
    'singletons' => [
        ClockInterface::class,
    ],
];
