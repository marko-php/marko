<?php

declare(strict_types=1);

it('creates valid package scaffolding with composer.json, module.php, config, and stubs', function (): void {
    $packageRoot = dirname(__DIR__);

    expect(file_exists($packageRoot . '/composer.json'))->toBeTrue()
        ->and(file_exists($packageRoot . '/module.php'))->toBeTrue()
        ->and(is_dir($packageRoot . '/config'))->toBeTrue()
        ->and(file_exists($packageRoot . '/config/tailwindcss.php'))->toBeTrue()
        ->and(is_dir($packageRoot . '/stubs/resources/css'))
        ->and(file_exists($packageRoot . '/stubs/resources/css/app.css'))->toBeTrue();
});

it('has a valid composer.json for marko/tailwindcss', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['name'])->toBe('marko/tailwindcss')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require']['marko/config'])->toBe('self.version')
        ->and($composer['require']['marko/core'])->toBe('self.version')
        ->and($composer['require']['marko/vite'])->toBe('self.version')
        ->and($composer['require-dev']['pestphp/pest'])->toBe('^4.0')
        ->and($composer['autoload']['psr-4']['Marko\\TailwindCss\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4']['Marko\\TailwindCss\\Tests\\'])->toBe('tests/');
});

it('has module.php with sequence and service bindings', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('sequence')
        ->and($module['sequence']['after'])->toContain('marko/vite')
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(Marko\TailwindCss\Contracts\TailwindPublisherInterface::class);
});
