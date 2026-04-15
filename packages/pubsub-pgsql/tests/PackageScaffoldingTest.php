<?php

declare(strict_types=1);

it('creates README.md for marko/pubsub-pgsql with all required sections', function (): void {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('## Overview')
        ->and($readme)->toContain('## Installation')
        ->and($readme)->toContain('## Usage')
        ->and($readme)->toContain('## API Reference');
});

it(
    'has valid module.php for marko/pubsub-pgsql binding PublisherInterface and SubscriberInterface',
    function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray()
            ->and($module['bindings'])->toHaveKey('Marko\\PubSub\\PublisherInterface')
            ->and($module['bindings']['Marko\\PubSub\\PublisherInterface'])->toBe(
                'Marko\\PubSub\\PgSql\\Driver\\PgSqlPublisher',
            )
            ->and($module['bindings'])->toHaveKey('Marko\\PubSub\\SubscriberInterface')
            ->and($module['bindings']['Marko\\PubSub\\SubscriberInterface'])->toBe(
                'Marko\\PubSub\\PgSql\\Driver\\PgSqlSubscriber',
            );
    },
);

it('provides default config file with pgsql connection settings', function (): void {
    $configPath = dirname(__DIR__) . '/config/pubsub-pgsql.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('host')
        ->and($config)->toHaveKey('port')
        ->and($config)->toHaveKey('user')
        ->and($config)->toHaveKey('password')
        ->and($config)->toHaveKey('database')
        ->and($config['host'])->toBe('127.0.0.1')
        ->and($config['port'])->toBe(5432);
});

it('has valid composer.json with name marko/pubsub-pgsql and required dependencies', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/pubsub-pgsql')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/pubsub')
        ->and($composer['require'])->toHaveKey('amphp/postgres')
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\PubSub\\PgSql\\')
        ->and($composer['autoload']['psr-4']['Marko\\PubSub\\PgSql\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\PubSub\\PgSql\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\PubSub\\PgSql\\Tests\\'])->toBe('tests/');
});
