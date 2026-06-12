<?php

declare(strict_types=1);

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Smtp\SocketInterface;
use Marko\Mail\Smtp\StreamSocket;

describe('module.php', function (): void {
    it('module.php exists with correct structure', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray();
    });

    it('module.php binds MailerInterface via factory', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $module = require $modulePath;

        expect($module['bindings'])->toHaveKey(MailerInterface::class)
            ->and($module['bindings'][MailerInterface::class])->toBeInstanceOf(Closure::class);
    });

    it('binds SocketInterface to the concrete StreamSocket in module.php', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $module = require $modulePath;

        expect($module['bindings'])->toHaveKey(SocketInterface::class)
            ->and($module['bindings'][SocketInterface::class])->toBe(StreamSocket::class);
    });
});

it('config/mail.php smtp section includes an auth_mode default', function (): void {
    $configPath = dirname(__DIR__, 4) . '/packages/mail/config/mail.php';
    $config = require $configPath;

    expect($config)->toHaveKey('smtp')
        ->and($config['smtp'])->toHaveKey('auth_mode')
        ->and($config['smtp']['auth_mode'])->toBe('login');
});
