<?php

declare(strict_types=1);

it('creates valid package scaffolding with composer.json, module.php, and config', function (): void {
    $packageDir = dirname(__DIR__);

    // composer.json exists and is valid
    $composerPath = $packageDir . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);
    expect(file_exists($composerPath))->toBeTrue()
        ->and($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/authentication-token')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/authentication')
        ->and($composer['require'])->toHaveKey('marko/database')
        ->and($composer['require'])->toHaveKey('marko/config');

    // module.php exists
    $modulePath = $packageDir . '/module.php';
    expect(file_exists($modulePath))->toBeTrue();

    // config/authentication-token.php exists with token_expiration_days
    $configPath = $packageDir . '/config/authentication-token.php';
    $config = require $configPath;
    expect(file_exists($configPath))->toBeTrue()
        ->and($config)->toBeArray()
        ->and($config)->toHaveKey('token_expiration_days');
});
