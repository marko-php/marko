<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\Redis\RedisFailedJobRepository;
use Marko\Queue\Redis\RedisQueue;
use Marko\Redis\RedisConnectionFactory;
use Marko\Redis\RedisConnectionFactoryInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;
use Psr\Clock\ClockInterface;

describe('queue-redis module', function (): void {
    it('binds QueueInterface and FailedJobRepositoryInterface to the Redis implementations', function (): void {
        $config = new FakeConfigRepository([
            'encryption.key' => 'module-test-key',
            'queue-redis.connection' => 'default',
            'queue-redis.prefix' => 'marko:',
            'queue-redis.block_for' => 0,
        ]);
        $container = new Container();
        $container->instance(ContainerInterface::class, $container);
        $container->instance(ConfigRepositoryInterface::class, $config);
        $container->instance(EventDispatcherInterface::class, new FakeEventDispatcher());
        $container->instance(ClockInterface::class, new FakeClock());
        $container->instance(LoggerInterface::class, new FakeLogger());
        $container->instance(RedisConnectionFactoryInterface::class, new RedisConnectionFactory($config));

        foreach (['queue', 'queue-redis'] as $package) {
            $module = require dirname(__DIR__, 2) . "/$package/module.php";

            foreach ($module['bindings'] as $interface => $implementation) {
                $container->bind($interface, $implementation);
            }
        }

        // Resolving builds the services without connecting: the connection opens on first use
        expect($container->get(QueueInterface::class))->toBeInstanceOf(RedisQueue::class)
            ->and($container->get(FailedJobRepositoryInterface::class))->toBeInstanceOf(
                RedisFailedJobRepository::class,
            );
    });

    it('ships connection, prefix and block_for config defaults', function (): void {
        $config = require dirname(__DIR__) . '/config/queue-redis.php';

        expect($config)->toBe([
            'connection' => 'default',
            'prefix' => 'marko:',
            'block_for' => 0,
        ]);
    });

    it('declares itself a marko module autoloading Marko\\Queue\\Redis from src/', function (): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

        expect($composer['name'])->toBe('marko/queue-redis')
            ->and($composer['type'])->toBe('marko-module')
            ->and($composer['extra']['marko']['module'])->toBeTrue()
            ->and($composer['autoload']['psr-4']['Marko\\Queue\\Redis\\'])->toBe('src/')
            ->and($composer['require'])->toHaveKeys(['marko/queue', 'marko/redis', 'predis/predis'])
            ->and($composer)->not->toHaveKey('version');
    });
});
