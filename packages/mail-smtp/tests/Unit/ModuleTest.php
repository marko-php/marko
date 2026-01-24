<?php

declare(strict_types=1);

use Marko\Mail\Contracts\MailerInterface;

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

    it('module.php binds MailerInterface via factory', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $module = require $modulePath;

        expect($module['bindings'])->toHaveKey(MailerInterface::class)
            ->and($module['bindings'][MailerInterface::class])->toBeInstanceOf(Closure::class);
    });
});
