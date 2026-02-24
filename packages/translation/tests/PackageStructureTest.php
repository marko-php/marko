<?php

declare(strict_types=1);
use Marko\Translation\Contracts\TranslatorInterface;

it('has valid composer.json with marko module flag and correct dependencies', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['name'])->toBe('marko/translation')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/config')
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer)->not->toHaveKey('version');
});

it('has PSR-4 autoloading configured for Marko\Translation namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Translation\\')
        ->and($composer['autoload']['psr-4']['Marko\\Translation\\'])->toBe('src/');
});

it('has module.php that binds TranslatorInterface to Translator', function () {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray()
        ->and($config['bindings'])->toHaveKey(TranslatorInterface::class);
});

it('provides default config file with locale and fallback_locale keys', function () {
    $configPath = dirname(__DIR__) . '/config/translation.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('locale')
        ->and($config)->toHaveKey('fallback_locale');
});

it('has src directory for source code', function () {
    expect(is_dir(dirname(__DIR__) . '/src'))->toBeTrue();
});

it('has tests directory for tests', function () {
    expect(is_dir(dirname(__DIR__) . '/tests'))->toBeTrue();
});

it('has config directory for default configuration', function () {
    expect(is_dir(dirname(__DIR__) . '/config'))->toBeTrue();
});
