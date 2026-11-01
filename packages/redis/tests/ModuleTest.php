<?php

declare(strict_types=1);

namespace Marko\Redis\Tests;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Redis\RedisConnectionFactory;
use Marko\Redis\RedisConnectionFactoryInterface;
use Marko\Testing\Fake\FakeConfigRepository;

describe('redis module', function (): void {
    it('binds RedisConnectionFactoryInterface to RedisConnectionFactory as a singleton', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository());

        foreach ($module['bindings'] as $id => $implementation) {
            $container->bind($id, $implementation);
        }

        foreach ($module['singletons'] as $id) {
            $container->singleton($id);
        }

        $factory = $container->get(RedisConnectionFactoryInterface::class);

        expect($factory)->toBeInstanceOf(RedisConnectionFactory::class)
            ->and($container->get(RedisConnectionFactoryInterface::class))->toBe($factory);
    });

    it('ships a default connection read from the REDIS_* environment variables', function (): void {
        $config = require dirname(__DIR__) . '/config/redis.php';

        expect($config['connections'])->toHaveKey('default')
            ->and($config['connections']['default'])->toHaveKeys(['host', 'port', 'password', 'database', 'scheme'])
            ->and($config['connections']['default']['port'])->toBeInt()
            ->and($config['connections']['default']['database'])->toBeInt()
            ->and($config['connections']['default']['scheme'])->toBe('tcp');
    });

    it('declares itself a marko module autoloading Marko\\Redis from src/', function (): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

        expect($composer['name'])->toBe('marko/redis')
            ->and($composer['extra']['marko']['module'])->toBeTrue()
            ->and($composer['autoload']['psr-4']['Marko\\Redis\\'])->toBe('src/')
            ->and($composer['require'])->toHaveKey('predis/predis');
    });
});
