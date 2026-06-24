<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;

return [
    'sequence' => ['after' => ['marko/page-cache']],
    'bindings' => [
        SessionHandlerInterface::class => DatabaseSessionHandler::class,
    ],
    'singletons' => [
        SessionInterface::class => Session::class,
    ],
    'globalMiddleware' => [
        SessionMiddleware::class,
    ],
];
