<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Handler\FileSessionHandler;

return [
    'bindings' => [
        SessionHandlerInterface::class => FileSessionHandler::class,
    ],
];
