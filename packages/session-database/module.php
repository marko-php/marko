<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;

return [
    'bindings' => [
        SessionHandlerInterface::class => DatabaseSessionHandler::class,
    ],
];
