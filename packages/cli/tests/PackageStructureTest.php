<?php

declare(strict_types=1);

$packageDir = dirname(__DIR__);

it('has valid composer.json with name marko/cli', function () use ($packageDir) {
    $composerPath = $packageDir . '/composer.json';
    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($composer['name'])->toBe('marko/cli');
});

it('has composer.json with description for CLI tool', function () use ($packageDir) {
    $composer = json_decode(file_get_contents($packageDir . '/composer.json'), true);
    expect($composer)->toHaveKey('description');
    expect($composer['description'])->toBe('Marko Framework CLI');
});

it('requires php ^8.5 in composer.json', function () use ($packageDir) {
    $composer = json_decode(file_get_contents($packageDir . '/composer.json'), true);
    expect($composer)->toHaveKey('require');
    expect($composer['require'])->toHaveKey('php');
    expect($composer['require']['php'])->toBe('^8.5');
});

it('has bin entry pointing to bin/marko in composer.json', function () use ($packageDir) {
    $composer = json_decode(file_get_contents($packageDir . '/composer.json'), true);
    expect($composer)->toHaveKey('bin');
    expect($composer['bin'])->toContain('bin/marko');
});

it('has PSR-4 autoload for Marko\\Cli namespace', function () use ($packageDir) {
    $composer = json_decode(file_get_contents($packageDir . '/composer.json'), true);
    expect($composer)->toHaveKey('autoload');
    expect($composer['autoload'])->toHaveKey('psr-4');
    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Cli\\');
    expect($composer['autoload']['psr-4']['Marko\\Cli\\'])->toBe('src/');
});

it('has src directory for source files', function () use ($packageDir) {
    expect(is_dir($packageDir . '/src'))->toBeTrue();
});

it('has bin directory for executable', function () use ($packageDir) {
    expect(is_dir($packageDir . '/bin'))->toBeTrue();
    expect(file_exists($packageDir . '/bin/marko'))->toBeTrue();
});

it('has tests directory structure', function () use ($packageDir) {
    expect(is_dir($packageDir . '/tests'))->toBeTrue();
    expect(file_exists($packageDir . '/tests/Pest.php'))->toBeTrue();
});
