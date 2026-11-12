<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Sse\SseConnectionLimiter;

return [
    'bindings' => [],
    'arguments' => [
        SseConnectionLimiter::class => [
            'maxConnections' => static function (ContainerInterface $container): ?int {
                $config = $container->get(ConfigRepositoryInterface::class);

                return $config->get(key: 'sse.max_connections') === null
                    ? null
                    : $config->getInt(key: 'sse.max_connections');
            },
            'retryAfter' => static fn (ContainerInterface $container): int => $container
                ->get(ConfigRepositoryInterface::class)
                ->getInt(key: 'sse.retry_after'),
        ],
    ],
];
