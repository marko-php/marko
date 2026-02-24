<?php

declare(strict_types=1);
use Marko\Notification\Database\Repository\DatabaseNotificationRepository;
use Marko\Notification\Database\Repository\NotificationRepositoryInterface;

describe('Package Scaffolding', function (): void {
    it('has marko module flag in composer.json', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['extra']['marko']['module'])->toBeTrue()
            ->and($composer['type'])->toBe('marko-module');
    });

    it('has correct PSR-4 autoloading namespace', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Notification\\Database\\')
            ->and($composer['autoload']['psr-4']['Marko\\Notification\\Database\\'])->toBe('src/')
            ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Notification\\Database\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Marko\\Notification\\Database\\Tests\\'])->toBe('tests/');
    });

    it('requires marko/notification and marko/database', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/notification')
            ->and($composer['require'])->toHaveKey('marko/database');
    });

    it('has no hardcoded version in composer.json', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toHaveKey('version');
    });

    it('binds NotificationRepositoryInterface to DatabaseNotificationRepository', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module)->toBeArray()
            ->and($module['enabled'])->toBeTrue()
            ->and($module['bindings'])->toBeArray()
            ->and($module['bindings'])->toHaveKey(
                NotificationRepositoryInterface::class,
            )
            ->and($module['bindings'][NotificationRepositoryInterface::class])
            ->toBe(DatabaseNotificationRepository::class);
    });
});
