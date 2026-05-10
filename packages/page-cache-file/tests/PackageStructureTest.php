<?php

declare(strict_types=1);

use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\File\Driver\FilePageCacheDriver;

it('has marko module flag in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('extra')
        ->and($composer['extra'])->toHaveKey('marko')
        ->and($composer['extra']['marko'])->toHaveKey('module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('declares correct PSR-4 autoloading namespace Marko\PageCache\File\\', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\PageCache\\File\\')
        ->and($composer['autoload']['psr-4']['Marko\\PageCache\\File\\'])->toBe('src/');
});

it('depends on marko/page-cache', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('require')
        ->and($composer['require'])->toHaveKey('marko/page-cache')
        ->and($composer['require']['marko/page-cache'])->toBe('self.version');
});

it('binds PageCacheInterface to FilePageCacheDriver in module.php', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toHaveKey(PageCacheInterface::class)
        ->and($config['bindings'][PageCacheInterface::class])->toBe(FilePageCacheDriver::class);
});

it('registers the package as a path repository in the root composer.json', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    $repositories = $composer['repositories'] ?? [];
    $paths = array_column($repositories, 'url');

    expect(in_array('packages/page-cache-file', $paths, true))->toBeTrue();
});

it('declares marko/page-cache-file as a self.version requirement in the root composer.json', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['require'])->toHaveKey('marko/page-cache-file')
        ->and($composer['require']['marko/page-cache-file'])->toBe('self.version');
});

it('registers the package test autoload as Marko\PageCache\File\Tests\\ in the root composer.json autoload-dev', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\PageCache\\File\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\PageCache\\File\\Tests\\'])->toBe('packages/page-cache-file/tests/');
});
