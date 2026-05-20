<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;

return [
    'singletons' => [
        SessionInterface::class => Session::class,
    ],
    'globalMiddleware' => [
        ['class' => SessionMiddleware::class, 'priority' => 20],
    ],
];
