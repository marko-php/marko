<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Log\Config\LogConfig;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\File\Handler\FileLogHandler;
use Marko\Log\File\Rotation\DailyRotation;
use Marko\Log\File\Rotation\RotationStrategyInterface;

return [
    'bindings' => [
        // marko/log binds LoggerInterface and resolves this handler when 'file' is in 'log.handlers'.
        FileLogHandler::class => function (ContainerInterface $container): FileLogHandler {
            $config = $container->get(LogConfig::class);

            return new FileLogHandler(
                path: $config->path(),
                formatter: $container->get(LogFormatterInterface::class),
                rotation: $container->get(RotationStrategyInterface::class),
                fileMode: $config->fileMode(),
                dirMode: $config->dirMode(),
            );
        },
        // Closure so DailyRotation is resolved by class name and a #[Preference] replacing it applies.
        RotationStrategyInterface::class => function (ContainerInterface $container): RotationStrategyInterface {
            return $container->get(DailyRotation::class);
        },
    ],
];
