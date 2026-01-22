<?php

declare(strict_types=1);

use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;

describe('Queue Module', function (): void {
    it('module.php exists with correct structure', function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $config = require $modulePath;

        expect($config)->toBeArray()
            ->and($config)->toHaveKey('enabled')
            ->and($config['enabled'])->toBeTrue()
            ->and($config)->toHaveKey('bindings')
            ->and($config['bindings'])->toBeArray();
    });

    it('module.php binds QueueConfig', function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(QueueConfig::class)
            ->and($config['bindings'][QueueConfig::class])->toBe(QueueConfig::class);
    });

    it('module.php does not bind QueueInterface', function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->not->toHaveKey(QueueInterface::class);
    });
});
