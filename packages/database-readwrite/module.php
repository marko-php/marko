<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\ReadWrite\Config\ReadWriteConnectionConfig;
use Marko\Database\ReadWrite\Connection\ReadWriteConnection;
use Marko\Database\ReadWrite\Replica\RandomReplicaSelector;
use Marko\Database\ReadWrite\Replica\WeightedReplicaSelector;

return [
    'bindings' => [],
    'boot' => function (ContainerInterface $container): void {
        $config = $container->get(ConfigRepositoryInterface::class);

        if ($config->get('database.driver') !== 'readwrite') {
            return;
        }

        $connections = $config->get('database.connections');
        $rwConfig = ReadWriteConnectionConfig::fromArray(['connections' => $connections]);

        $factory = $container->get(ConnectionFactoryInterface::class);
        $writeConnection = $factory->make(DatabaseConfig::fromArray($rwConfig->write));

        $replicaConnections = array_map(
            fn (array $replicaConfig) => $factory->make(DatabaseConfig::fromArray($replicaConfig)),
            $rwConfig->reads,
        );

        $selector = match ($rwConfig->readStrategy) {
            'weighted' => new WeightedReplicaSelector(
                array_map(fn (array $r) => $r['weight'] ?? 1, $rwConfig->reads),
            ),
            default => new RandomReplicaSelector(),
        };

        $readWriteConnection = new ReadWriteConnection($writeConnection, $replicaConnections, $selector);
        $container->instance(ConnectionInterface::class, $readWriteConnection);
        $container->instance(TransactionInterface::class, $readWriteConnection);
    },
];
