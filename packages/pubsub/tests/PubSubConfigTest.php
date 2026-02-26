<?php

declare(strict_types=1);

use Marko\PubSub\PubSubConfig;
use Marko\Testing\Fake\FakeConfigRepository;

describe('PubSubConfig', function (): void {
    it('creates PubSubConfig wrapping ConfigRepositoryInterface', function (): void {
        $config = new FakeConfigRepository([
            'pubsub.driver' => 'redis',
        ]);

        $pubSubConfig = new PubSubConfig($config);

        expect($pubSubConfig)->toBeInstanceOf(PubSubConfig::class);
    });

    it('reads driver from pubsub.driver config key', function (): void {
        $config = new FakeConfigRepository([
            'pubsub.driver' => 'redis',
        ]);

        $pubSubConfig = new PubSubConfig($config);

        expect($pubSubConfig->driver())->toBe('redis');
    });

    it('reads prefix from pubsub.prefix config key', function (): void {
        $config = new FakeConfigRepository([
            'pubsub.prefix' => 'myapp:',
        ]);

        $pubSubConfig = new PubSubConfig($config);

        expect($pubSubConfig->prefix())->toBe('myapp:');
    });

    it('provides default config file with driver and prefix values', function (): void {
        $configFile = dirname(__DIR__) . '/config/pubsub.php';

        expect(file_exists($configFile))->toBeTrue();

        $defaults = require $configFile;

        expect($defaults)->toBeArray()
            ->and($defaults)->toHaveKey('driver')
            ->and($defaults)->toHaveKey('prefix')
            ->and($defaults['driver'])->toBe('redis')
            ->and($defaults['prefix'])->toBe('marko:');
    });
});
