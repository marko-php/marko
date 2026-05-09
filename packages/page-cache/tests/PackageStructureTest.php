<?php

declare(strict_types=1);

it('has marko module flag in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('extra')
        ->and($composer['extra'])->toHaveKey('marko')
        ->and($composer['extra']['marko'])->toHaveKey('module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('declares correct PSR-4 autoloading namespace Marko\PageCache\\', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\PageCache\\')
        ->and($composer['autoload']['psr-4']['Marko\\PageCache\\'])->toBe('src/');
});

it('depends on marko/core, marko/config, and marko/routing', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/config')
        ->and($composer['require'])->toHaveKey('marko/routing');
});

it('registers the package test autoload in the root composer.json autoload-dev', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\PageCache\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\PageCache\\Tests\\'])->toBe('packages/page-cache/tests/');
});

it('registers the package as a path repository in the root composer.json', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    $urls = array_column($composer['repositories'], 'url');

    expect($urls)->toContain('packages/page-cache');
});

it('declares marko/page-cache as a self.version requirement in the root composer.json', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['require'])->toHaveKey('marko/page-cache')
        ->and($composer['require']['marko/page-cache'])->toBe('self.version');
});
