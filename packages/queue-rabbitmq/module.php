<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobIdGenerator;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\Exceptions\RabbitmqException;
use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;
use Marko\Queue\Rabbitmq\RabbitmqConnection;
use Marko\Queue\Rabbitmq\RabbitmqFailedJobRepository;
use Marko\Queue\Rabbitmq\RabbitmqQueue;

return [
    'bindings' => [
        QueueInterface::class => static function (ContainerInterface $container): QueueInterface {
            return new RabbitmqQueue(
                connection: $container->get(RabbitmqConnection::class),
                exchangeConfig: $container->get(ExchangeConfig::class),
                jobEnvelope: $container->get(JobEnvelope::class),
                defaultQueue: $container->get(QueueConfig::class)->queue(),
                jobIdGenerator: $container->get(JobIdGenerator::class),
            );
        },
        FailedJobRepositoryInterface::class => RabbitmqFailedJobRepository::class,
        RabbitmqConnection::class => static function (ContainerInterface $container): RabbitmqConnection {
            $config = $container->get(ConfigRepositoryInterface::class);
            $tls = $config->get(key: 'queue-rabbitmq.tls');

            return new RabbitmqConnection(
                host: $config->getString(key: 'queue-rabbitmq.host'),
                port: $config->getInt(key: 'queue-rabbitmq.port'),
                user: $config->getString(key: 'queue-rabbitmq.user'),
                password: $config->getString(key: 'queue-rabbitmq.password'),
                vhost: $config->getString(key: 'queue-rabbitmq.vhost'),
                tlsOptions: $tls === null ? null : $config->getArray(key: 'queue-rabbitmq.tls'),
            );
        },
        ExchangeConfig::class => static function (ContainerInterface $container): ExchangeConfig {
            $config = $container->get(ConfigRepositoryInterface::class);
            $type = $config->getString(key: 'queue-rabbitmq.exchange.type');

            return new ExchangeConfig(
                name: $config->getString(key: 'queue-rabbitmq.exchange.name'),
                type: ExchangeType::tryFrom($type) ?? throw RabbitmqException::invalidExchangeType(
                    $type,
                    array_map(static fn (ExchangeType $case): string => $case->value, ExchangeType::cases()),
                ),
                durable: $config->getBool(key: 'queue-rabbitmq.exchange.durable'),
                autoDelete: $config->getBool(key: 'queue-rabbitmq.exchange.auto_delete'),
            );
        },
    ],
    'singletons' => [
        RabbitmqConnection::class,
    ],
];
