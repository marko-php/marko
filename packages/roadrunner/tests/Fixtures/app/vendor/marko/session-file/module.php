<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\File\Handler\FileSessionHandler;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;

// Mirrors packages/session-file/module.php — this is precisely the module
// task 005/006 need present: SessionInterface bound as a singleton and
// SessionMiddleware registered globally, so cross-request leaks of session
// state are directly observable through the harness.
return [
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
