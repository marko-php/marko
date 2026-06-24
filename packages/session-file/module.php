<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\File\Handler\FileSessionHandler;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;

return [
    'sequence' => ['after' => ['marko/page-cache']],
    'bindings' => [
        SessionHandlerInterface::class => FileSessionHandler::class,
    ],
    'singletons' => [
        SessionInterface::class => Session::class,
    ],
    'globalMiddleware' => [
        SessionMiddleware::class,
    ],
];
