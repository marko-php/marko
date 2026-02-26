<?php

declare(strict_types=1);

it('creates README.md for marko/pubsub-redis with all required sections', function (): void {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('## Overview')
        ->and($readme)->toContain('## Installation')
        ->and($readme)->toContain('## Usage')
        ->and($readme)->toContain('## API Reference');
});

it(
    'has valid module.php for marko/pubsub-redis binding PublisherInterface and SubscriberInterface',
    function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';
    
        expect(file_exists($modulePath))->toBeTrue();
    
        $module = require $modulePath;
    
        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray()
            ->and($module['bindings'])->toHaveKey('Marko\\PubSub\\PublisherInterface')
            ->and($module['bindings']['Marko\\PubSub\\PublisherInterface'])->toBe(
                'Marko\\PubSub\\Redis\\Driver\\RedisPublisher'
            )
            ->and($module['bindings'])->toHaveKey('Marko\\PubSub\\SubscriberInterface')
            ->and($module['bindings']['Marko\\PubSub\\SubscriberInterface'])->toBe(
                'Marko\\PubSub\\Redis\\Driver\\RedisSubscriber'
            );
    }
);

it('has valid composer.json with name marko/pubsub-redis and required dependencies', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/pubsub-redis')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/pubsub')
        ->and($composer['require'])->toHaveKey('amphp/redis')
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\PubSub\\Redis\\')
        ->and($composer['autoload']['psr-4']['Marko\\PubSub\\Redis\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\PubSub\\Redis\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\PubSub\\Redis\\Tests\\'])->toBe('tests/');
});

it('provides default config file with redis connection settings', function (): void {
    $configPath = dirname(__DIR__) . '/config/pubsub-redis.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('host')
        ->and($config)->toHaveKey('port')
        ->and($config)->toHaveKey('password')
        ->and($config)->toHaveKey('database')
        ->and($config['host'])->toBe('127.0.0.1')
        ->and($config['port'])->toBe(6379)
        ->and($config['password'])->toBeNull()
        ->and($config['database'])->toBe(0);
});
