<?php

declare(strict_types=1);
use Marko\Translation\Contracts\TranslationLoaderInterface;

it('has valid composer.json with marko module flag and correct dependencies', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['name'])->toBe('marko/translation-file')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/config')
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer)->not->toHaveKey('version');
});

it('requires marko/translation as a dependency', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/translation');
});

it('has PSR-4 autoload for Marko\Translation\File namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Translation\\File\\')
        ->and($composer['autoload']['psr-4']['Marko\\Translation\\File\\'])->toBe('src/');
});

it('has module.php that binds TranslationLoaderInterface to FileTranslationLoader', function () {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray()
        ->and($config['bindings'])->toHaveKey(TranslationLoaderInterface::class);
});

it('returns valid module configuration array', function () {
    $modulePath = dirname(__DIR__) . '/module.php';
    $config = require $modulePath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray();
});

it('has src directory for source code', function () {
    expect(is_dir(dirname(__DIR__) . '/src'))->toBeTrue();
});

it('has tests directory for tests', function () {
    expect(is_dir(dirname(__DIR__) . '/tests'))->toBeTrue();
});
