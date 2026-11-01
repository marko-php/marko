<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\Formatter\LogFormatterFactory;
use Marko\Log\LoggerFactory;

return [
    'bindings' => [
        // One shared Logger; handler packages (marko/log-file, marko/log-stream, ...) only add handlers.
        LoggerInterface::class => function (ContainerInterface $container): LoggerInterface {
            return $container->get(LoggerFactory::class)->create();
        },
        LogFormatterInterface::class => function (ContainerInterface $container): LogFormatterInterface {
            return $container->get(LogFormatterFactory::class)->create();
        },
    ],
];
