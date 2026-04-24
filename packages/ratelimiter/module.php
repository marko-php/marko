<?php

declare(strict_types=1);

use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\RateLimiter;

return [
    'bindings' => [
        RateLimiterInterface::class => RateLimiter::class,
    ],
];
