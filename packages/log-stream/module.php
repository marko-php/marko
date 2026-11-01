<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Log\Stream\Handler\StreamLogHandler;
use Marko\Log\Stream\Handler\StreamLogHandlerFactory;

return [
    'bindings' => [
        // marko/log binds LoggerInterface and resolves this handler when 'stream' is in 'log.handlers'.
        StreamLogHandler::class => function (ContainerInterface $container): StreamLogHandler {
            return $container->get(StreamLogHandlerFactory::class)->create();
        },
    ],
];
