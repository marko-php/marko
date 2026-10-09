<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Driver\AmphpBroadcaster;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\BroadcasterInterface;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeConfigRepository;

describe('broadcasting-amphp module', function (): void {
    it('binds BroadcasterInterface to AmphpBroadcaster', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'][BroadcasterInterface::class])->toBe(AmphpBroadcaster::class)
            ->and($module['singletons'])->toContain(AmphpBroadcastingConfig::class);
    });

    it('builds AmphpBroadcastingConfig from the broadcasting-amphp config', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $defaults = require dirname(__DIR__) . '/config/broadcasting-amphp.php';

        $values = [];
        foreach ([...$defaults, 'port' => 9000, 'app_key' => 'secret', 'allowed_origins' => ['https://app.test']] as $key => $value) {
            $values["broadcasting-amphp.$key"] = $value;
        }

        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository($values));

        $config = $module['bindings'][AmphpBroadcastingConfig::class]($container);

        expect($config)->toBeInstanceOf(AmphpBroadcastingConfig::class)
            ->and($config->host)->toBe('0.0.0.0')
            ->and($config->port)->toBe(9000)
            ->and($config->path)->toBe('/stream')
            ->and($config->healthPath)->toBe('/health')
            ->and($config->healthDetail)->toBeFalse()
            ->and($config->healthSecret)->toBe('')
            ->and($config->appKey)->toBe('secret')
            ->and($config->heartbeat)->toBe(15)
            ->and($config->replayBuffer)->toBe(100)
            ->and($config->replayTtl)->toBe(300)
            ->and($config->maxConnections)->toBe(10000)
            ->and($config->maxConnectionsPerIp)->toBe(100)
            ->and($config->allowedOrigins)->toBe(['https://app.test'])
            ->and($config->trustedProxies)->toBeEmpty()
            ->and($config->tokenTtl)->toBe(3600)
            ->and($config->logInterval)->toBe(60);
    });

    it('builds a config with health detail enabled when a health secret is set', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $defaults = require dirname(__DIR__) . '/config/broadcasting-amphp.php';

        $values = [];
        foreach ([...$defaults, 'health_detail' => true, 'health_secret' => 'health-secret'] as $key => $value) {
            $values["broadcasting-amphp.$key"] = $value;
        }

        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository($values));

        $config = $module['bindings'][AmphpBroadcastingConfig::class]($container);

        expect($config->healthDetail)->toBeTrue()
            ->and($config->healthSecret)->toBe('health-secret');
    });

    it('refuses to build a config with health detail enabled and no health secret', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $defaults = require dirname(__DIR__) . '/config/broadcasting-amphp.php';

        $values = [];
        foreach ([...$defaults, 'health_detail' => true] as $key => $value) {
            $values["broadcasting-amphp.$key"] = $value;
        }

        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository($values));

        expect(fn () => $module['bindings'][AmphpBroadcastingConfig::class]($container))
            ->toThrow(AmphpBroadcastException::class, 'Health detail is enabled but no health secret is configured.');
    });

    it('ships defaults for every config key', function (): void {
        $defaults = require dirname(__DIR__) . '/config/broadcasting-amphp.php';

        expect(array_keys($defaults))->toBe([
            'host',
            'port',
            'path',
            'health_path',
            'health_detail',
            'health_secret',
            'public_url',
            'channel_prefix',
            'app_key',
            'heartbeat',
            'replay_buffer',
            'replay_ttl',
            'max_connections',
            'max_connections_per_ip',
            'allowed_origins',
            'trusted_proxies',
            'token_ttl',
            'log_interval',
        ])->and($defaults['allowed_origins'])->toBe(['*'])
            ->and($defaults['health_detail'])->toBeFalse()
            ->and($defaults['health_secret'])->toBe('');
    });

    it('has a valid composer.json requiring broadcasting, amphp, pubsub and http-server', function (): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

        expect($composer['name'])->toBe('marko/broadcasting-amphp')
            ->and($composer['type'])->toBe('marko-module')
            ->and($composer)->not->toHaveKey('version')
            ->and($composer['require'])->toHaveKeys([
                'marko/broadcasting',
                'marko/amphp',
                'marko/pubsub',
                'marko/log',
                'amphp/http-server',
            ])
            ->and($composer['autoload']['psr-4'])->toBe(['Marko\\Broadcasting\\Amphp\\' => 'src/'])
            ->and($composer['extra']['marko']['module'])->toBeTrue();
    });
});
