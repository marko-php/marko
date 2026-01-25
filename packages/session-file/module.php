<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Handler\FileSessionHandler;

return [
    'enabled' => true,
    'bindings' => [
        SessionHandlerInterface::class => FileSessionHandler::class,
    ],
];
