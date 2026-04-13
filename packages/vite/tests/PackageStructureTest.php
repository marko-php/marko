<?php

declare(strict_types=1);

it('creates valid package scaffolding with composer.json, module.php, and config', function (): void {
    $packageRoot = dirname(__DIR__);

    expect(file_exists($packageRoot . '/composer.json'))->toBeTrue()
        ->and(file_exists($packageRoot . '/module.php'))->toBeTrue()
        ->and(is_dir($packageRoot . '/config'))->toBeTrue()
        ->and(file_exists($packageRoot . '/config/vite.php'))->toBeTrue();
});

it('has a valid composer.json for marko/vite', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['name'])->toBe('marko/vite')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require']['marko/config'])->toBe('self.version')
        ->and($composer['require']['marko/core'])->toBe('self.version')
        ->and($composer['require-dev']['pestphp/pest'])->toBe('^4.0')
        ->and($composer['autoload']['psr-4']['Marko\\Vite\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Vite\\Tests\\'])->toBe('tests/');
});

it('has module.php with bindings for the vite services', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(Marko\Vite\Contracts\ViteManagerInterface::class)
        ->and($module['bindings'])->toHaveKey(Marko\Vite\Contracts\VitePublisherInterface::class);
});

it('publishes a base vite config helper with root and module alias support', function (): void {
    $helper = (string) file_get_contents(dirname(__DIR__) . '/resources/config/createViteConfig.ts');

    expect($helper)->toContain('resolve: {')
        ->and($helper)->toContain('alias: createMarkoAliases(projectRoot)')
        ->and($helper)->toContain('watch: {')
        ->and($helper)->toContain('ignored: createIgnoredWatchPatterns()')
        ->and($helper)->toContain('"**/storage/**"')
        ->and($helper)->toContain("find: /^@\\//")
        ->and($helper)->toContain('discoverModuleAliases(projectRoot)')
        ->and($helper)->toContain('registerModulesDirectoryAliases')
        ->and($helper)->toContain('registerAppDirectoryAliases');
});
