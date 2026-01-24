<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Log\Config\LogConfig;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Formatter\LineFormatter;

return [
    'enabled' => true,
    'bindings' => [
        LogFormatterInterface::class => function (ContainerInterface $container): LogFormatterInterface {
            $config = $container->get(LogConfig::class);

            return new LineFormatter(
                format: $config->format(),
                dateFormat: $config->dateFormat(),
            );
        },
    ],
];
