<?php

declare(strict_types=1);

use Marko\Mail\Config\MailConfig;

describe('module.php', function (): void {
    it('module.php exists with correct structure', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('enabled')
            ->and($module['enabled'])->toBeTrue()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray();
    });

    it('module.php binds MailConfig', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $module = require $modulePath;

        expect($module['bindings'])->toHaveKey(MailConfig::class)
            ->and($module['bindings'][MailConfig::class])->toBe(MailConfig::class);
    });

    it('module.php does not bind MailerInterface', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $module = require $modulePath;

        // MailerInterface should be bound by drivers, not the base mail package
        expect($module['bindings'])->not->toHaveKey('Marko\\Mail\\MailerInterface');
    });
});
