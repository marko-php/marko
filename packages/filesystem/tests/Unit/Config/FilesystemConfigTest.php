<?php

declare(strict_types=1);

use Marko\Filesystem\Config\FilesystemConfig;
use Marko\Testing\Fake\FakeConfigRepository;

describe('FilesystemConfig', function (): void {
    it('reads default from config without fallback', function (): void {
        $config = new FilesystemConfig(new FakeConfigRepository([
            'filesystem.default' => 'local',
        ]));

        expect($config->defaultDisk())->toBe('local');
    });

    it('reads disk configuration from config without fallback', function (): void {
        $diskConfig = [
            'local' => [
                'driver' => 'local',
                'path' => 'storage',
            ],
        ];

        $config = new FilesystemConfig(new FakeConfigRepository([
            'filesystem.disks' => $diskConfig,
        ]));

        expect($config->getDisk('local'))->toBe($diskConfig['local']);
    });

    it('config file contains all required keys with defaults', function (): void {
        $configFile = require dirname(__DIR__, 3) . '/config/filesystem.php';

        expect($configFile)->toBeArray()
            ->and($configFile)->toHaveKey('default')
            ->and($configFile)->toHaveKey('disks')
            ->and($configFile['default'])->toBeString()
            ->and($configFile['disks'])->toBeArray();
    });
});
