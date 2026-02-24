<?php

declare(strict_types=1);

use Marko\RateLimiting\Contracts\RateLimiterInterface;
use Marko\RateLimiting\RateLimiter;

return [
    'bindings' => [
        RateLimiterInterface::class => RateLimiter::class,
    ],
];
