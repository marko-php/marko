<?php

declare(strict_types=1);

use Marko\Amphp\AmphpConfig;
use Marko\Testing\Fake\FakeConfigRepository;

describe('AmphpConfig', function (): void {
    it('creates AmphpConfig wrapping ConfigRepositoryInterface', function (): void {
        $config = new FakeConfigRepository([
            'amphp.shutdown_timeout' => 30,
        ]);

        $amphpConfig = new AmphpConfig($config);

        expect($amphpConfig)->toBeInstanceOf(AmphpConfig::class);
    });

    it('reads shutdown timeout from amphp.shutdown_timeout config key', function (): void {
        $config = new FakeConfigRepository([
            'amphp.shutdown_timeout' => 60,
        ]);

        $amphpConfig = new AmphpConfig($config);

        expect($amphpConfig->shutdownTimeout())->toBe(60);
    });

    it('provides default config file with shutdown_timeout value', function (): void {
        $configPath = dirname(__DIR__) . '/config/amphp.php';

        expect(file_exists($configPath))->toBeTrue();

        $configData = require $configPath;

        expect($configData)->toBeArray()
            ->and($configData)->toHaveKey('shutdown_timeout')
            ->and($configData['shutdown_timeout'])->toBeInt();
    });
});
