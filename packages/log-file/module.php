<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Log\Config\LogConfig;
use Marko\Log\File\Handler\FileLogHandler;
use Marko\Log\File\Rotation\DailyRotation;
use Marko\Log\File\Rotation\RotationStrategyInterface;

return [
    'bindings' => [
        // Closure so DailyRotation is resolved by class name and a #[Preference] replacing it applies.
        RotationStrategyInterface::class => function (ContainerInterface $container): RotationStrategyInterface {
            return $container->get(DailyRotation::class);
        },
    ],
    // marko/log binds LoggerInterface and resolves this handler when 'file' is in 'log.handlers'.
    'arguments' => [
        FileLogHandler::class => [
            'path' => fn (ContainerInterface $container): string => $container->get(LogConfig::class)->path(),
            'fileMode' => fn (ContainerInterface $container): int => $container->get(LogConfig::class)->fileMode(),
            'dirMode' => fn (ContainerInterface $container): int => $container->get(LogConfig::class)->dirMode(),
        ],
    ],
];
