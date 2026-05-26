<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;

return [
    'sequence' => [
        // page-cache must short-circuit before session work begins; soft
        // ordering — only enforced when marko/page-cache is also installed.
        'after' => ['marko/page-cache'],
    ],
    'singletons' => [
        SessionInterface::class => Session::class,
    ],
    'globalMiddleware' => [
        SessionMiddleware::class,
    ],
];
