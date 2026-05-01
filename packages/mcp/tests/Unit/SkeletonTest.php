<?php

declare(strict_types=1);

it('has composer.json with name marko/mcp and dependencies on codeindexer and docs', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect(file_exists($composerPath))->toBeTrue()
        ->and($composer['name'])->toBe('marko/mcp')
        ->and($composer['require'])->toHaveKey('marko/codeindexer')
        ->and($composer['require'])->toHaveKey('marko/docs')
        ->and($composer['require'])->toHaveKey('marko/cli')
        ->and($composer['require'])->toHaveKey('marko/core');
});

it('has src tests/Unit tests/Feature directories with Pest bootstrap', function (): void {
    $base = dirname(__DIR__, 2);

    expect(is_dir($base . '/src'))->toBeTrue()
        ->and(is_dir($base . '/tests/Unit'))->toBeTrue()
        ->and(is_dir($base . '/tests/Feature'))->toBeTrue()
        ->and(file_exists($base . '/tests/Pest.php'))->toBeTrue();

    $pestContents = file_get_contents($base . '/tests/Pest.php');
    expect($pestContents)->toContain('declare(strict_types=1)');
});

it('autoloads cleanly with composer dump-autoload', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Mcp\\')
        ->and($composer['autoload']['psr-4']['Marko\\Mcp\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Mcp\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Mcp\\Tests\\'])->toBe('tests/');
});

it('has module.php returning a manifest with bindings and singletons keys', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray()
        ->and($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toBeArray();
});
