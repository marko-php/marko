<?php

declare(strict_types=1);

it('creates valid package scaffolding with composer.json, module.php, and config', function (): void {
    $packageRoot = dirname(__DIR__);

    // composer.json
    $composerPath = $packageRoot . '/composer.json';
    expect(file_exists($composerPath))->toBeTrue();
    $composer = json_decode(file_get_contents($composerPath), true);
    expect($composer)->not->toBeNull()
        ->and($composer['name'])->toBe('marko/media')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/filesystem')
        ->and($composer['require'])->toHaveKey('marko/database')
        ->and($composer['require'])->toHaveKey('marko/validation')
        ->and($composer['require'])->toHaveKey('marko/config')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Media\\')
        ->and($composer['extra']['marko']['module'])->toBeTrue();

    // module.php
    $modulePath = $packageRoot . '/module.php';
    expect(file_exists($modulePath))->toBeTrue();
    $module = require $modulePath;
    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings');

    // config/media.php
    $configPath = $packageRoot . '/config/media.php';
    expect(file_exists($configPath))->toBeTrue();
    $config = require $configPath;
    expect($config)->toBeArray()
        ->and($config)->toHaveKey('disk')
        ->and($config)->toHaveKey('max_file_size')
        ->and($config)->toHaveKey('allowed_mime_types')
        ->and($config)->toHaveKey('allowed_extensions')
        ->and($config)->toHaveKey('url_prefix')
        ->and($config['disk'])->toBe('local')
        ->and($config['max_file_size'])->toBe(10485760)
        ->and($config['allowed_mime_types'])->toBe(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
        ->and($config['allowed_extensions'])->toBe(['jpg', 'jpeg', 'png', 'gif', 'webp'])
        ->and($config['url_prefix'])->toBe('/storage');
});
