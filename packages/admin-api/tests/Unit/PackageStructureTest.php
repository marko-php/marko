<?php

declare(strict_types=1);

it('has valid composer.json with admin, admin-auth, routing, auth dependencies', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/admin-api')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require']['marko/core'])->toBe('@dev')
        ->and($composer['require'])->toHaveKey('marko/admin')
        ->and($composer['require']['marko/admin'])->toBe('@dev')
        ->and($composer['require'])->toHaveKey('marko/admin-auth')
        ->and($composer['require']['marko/admin-auth'])->toBe('@dev')
        ->and($composer['require'])->toHaveKey('marko/routing')
        ->and($composer['require']['marko/routing'])->toBe('@dev')
        ->and($composer['require'])->toHaveKey('marko/authentication')
        ->and($composer['require']['marko/authentication'])->toBe('@dev')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\AdminApi\\')
        ->and($composer['autoload']['psr-4']['Marko\\AdminApi\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\AdminApi\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\AdminApi\\Tests\\'])->toBe('tests/')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('has valid module.php with bindings', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray();
});
