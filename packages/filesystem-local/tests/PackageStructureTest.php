<?php

declare(strict_types=1);

use Marko\Filesystem\Attributes\FilesystemDriver;
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Local\Factory\LocalFilesystemFactory;

it('has a valid composer.json with correct package name marko/filesystem-local', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/filesystem-local');
});

it('has correct description in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['description'])->toBe('Local filesystem driver for Marko Framework');
});

it('has type marko-module in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module');
});

it('has MIT license in composer.json', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['license'])->toBe('MIT');
});

it('requires PHP 8.5 or higher', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5');
});

it('requires marko/filesystem package', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/filesystem');
});

it('has PSR-4 autoloading configured for Marko\\Filesystem\\Local namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Filesystem\\Local\\')
        ->and($composer['autoload']['psr-4']['Marko\\Filesystem\\Local\\'])->toBe('src/');
});

it('has src directory for source code', function () {
    $srcPath = dirname(__DIR__) . '/src';

    expect(is_dir($srcPath))->toBeTrue();
});

it('has tests directory for tests', function () {
    $testsPath = dirname(__DIR__) . '/tests';

    expect(is_dir($testsPath))->toBeTrue();
});

it('has LocalFilesystemFactory with FilesystemDriver attribute', function () {
    $reflection = new ReflectionClass(LocalFilesystemFactory::class);
    $attributes = $reflection->getAttributes(FilesystemDriver::class);

    expect($attributes)->toHaveCount(1);

    $attribute = $attributes[0]->newInstance();

    expect($attribute->name)->toBe('local');
});

it('has LocalFilesystemFactory implementing FilesystemDriverFactoryInterface', function () {
    $reflection = new ReflectionClass(LocalFilesystemFactory::class);

    expect($reflection->implementsInterface(FilesystemDriverFactoryInterface::class))->toBeTrue();
});
