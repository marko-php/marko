<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Module;

use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Sending\WebhookDispatcher;

describe('Webhook package scaffolding', function (): void {
    it('creates valid package scaffolding with composer.json, module.php, and config', function (): void {
        $basePath = dirname(__DIR__, 2);

        // composer.json exists and is valid
        $composerPath = $basePath . '/composer.json';
        expect(file_exists($composerPath))->toBeTrue();
        $composer = json_decode(file_get_contents($composerPath), true);
        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/webhook')
            ->and($composer['require'])->toHaveKey('marko/http')
            ->and($composer['require'])->toHaveKey('marko/queue')
            ->and($composer['require'])->toHaveKey('marko/config');

        // module.php exists and defines bindings
        $modulePath = $basePath . '/module.php';
        expect(file_exists($modulePath))->toBeTrue();
        $module = require $modulePath;
        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toHaveKey(WebhookDispatcherInterface::class)
            ->and($module['bindings'][WebhookDispatcherInterface::class])->toBe(WebhookDispatcher::class);

        // config/webhook.php exists with required keys
        $configPath = $basePath . '/config/webhook.php';
        expect(file_exists($configPath))->toBeTrue();
        $config = require $configPath;
        expect($config)->toBeArray()
            ->and($config)->toHaveKey('timeout')
            ->and($config['timeout'])->toBe(30)
            ->and($config)->toHaveKey('max_retries')
            ->and($config['max_retries'])->toBe(3)
            ->and($config)->toHaveKey('retry_delay')
            ->and($config['retry_delay'])->toBe(60);
    });
});
