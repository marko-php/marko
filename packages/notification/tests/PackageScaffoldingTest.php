<?php

declare(strict_types=1);

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

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Notification\\')
            ->and($composer['autoload']['psr-4']['Marko\\Notification\\'])->toBe('src/')
            ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Notification\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Marko\\Notification\\Tests\\'])->toBe('tests/');
    });

    it('requires marko/core and marko/config', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/core')
            ->and($composer['require'])->toHaveKey('marko/config');
    });

    it('has no hardcoded version in composer.json', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toHaveKey('version');
    });

    it('returns valid module configuration array with bindings', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('enabled')
            ->and($module['enabled'])->toBeTrue()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray()
            ->and($module)->toHaveKey('boot')
            ->and($module['boot'])->toBeCallable();
    });
});
