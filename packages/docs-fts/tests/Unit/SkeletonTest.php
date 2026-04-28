<?php

declare(strict_types=1);
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsFts\FtsSearch;

it('has composer.json with name marko/docs-fts and dependencies on marko/docs and marko/docs-markdown', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect(file_exists($composerPath))->toBeTrue()
        ->and($composer['name'])->toBe('marko/docs-fts')
        ->and($composer['require'])->toHaveKey('marko/docs')
        ->and($composer['require'])->toHaveKey('marko/docs-markdown');
});

it('declares ext-pdo_sqlite as a required PHP extension', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('ext-pdo_sqlite');
});

it('has module.php binding DocsSearchInterface to FtsSearch', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toHaveKey(DocsSearchInterface::class)
        ->and($module['singletons'][DocsSearchInterface::class])->toBeInstanceOf(Closure::class);
});

it('has src tests/Unit tests/Feature directories with Pest bootstrap', function (): void {
    $base = dirname(__DIR__, 2);

    expect(is_dir($base . '/src'))->toBeTrue()
        ->and(is_dir($base . '/tests/Unit'))->toBeTrue()
        ->and(is_dir($base . '/tests/Feature'))->toBeTrue()
        ->and(file_exists($base . '/tests/Pest.php'))->toBeTrue();
});

it('autoloads cleanly with composer dump-autoload', function (): void {
    expect(class_exists(FtsSearch::class))->toBeTrue()
        ->and(FtsSearch::class)->toImplement(DocsSearchInterface::class);
});
