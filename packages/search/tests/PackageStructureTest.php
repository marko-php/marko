<?php

declare(strict_types=1);

it('creates valid package scaffolding with composer.json, module.php, and config', function (): void {
    $packageRoot = dirname(__DIR__);

    expect(file_exists($packageRoot . '/composer.json'))->toBeTrue()
        ->and(file_exists($packageRoot . '/module.php'))->toBeTrue()
        ->and(is_dir($packageRoot . '/config'))->toBeTrue()
        ->and(file_exists($packageRoot . '/config/search.php'))->toBeTrue();

    $composer = json_decode(file_get_contents($packageRoot . '/composer.json'), true);
    $module = require $packageRoot . '/module.php';
    $config = require $packageRoot . '/config/search.php';

    expect($composer['name'])->toBe('marko/search')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer)->not->toHaveKey('version')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Search\\')
        ->and($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($config)->toBeArray()
        ->and($config)->toHaveKey('default_per_page')
        ->and($config['default_per_page'])->toBe(15)
        ->and($config)->toHaveKey('max_per_page')
        ->and($config['max_per_page'])->toBe(100);
});
