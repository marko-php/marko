<?php

declare(strict_types=1);

it('has valid composer.json with correct name', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/framework');
});

it('is type metapackage', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('type')
        ->and($composer['type'])->toBe('metapackage');
});

it('requires PHP 8.5', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('require')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5');
});

it('requires core packages', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $requiredPackages = [
        'marko/core',
        'marko/routing',
        'marko/cli',
        'marko/errors',
        'marko/errors-simple',
        'marko/config',
        'marko/hashing',
        'marko/validation',
    ];

    expect($composer)->toHaveKey('require');

    foreach ($requiredPackages as $package) {
        expect($composer['require'])->toHaveKey($package);
        expect($composer['require'][$package])->toBe('^1.0');
    }
});

it('suggests optional packages', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $suggestedPackages = [
        'marko/database',
        'marko/cache',
        'marko/session',
        'marko/auth',
        'marko/log',
        'marko/filesystem',
        'marko/errors-advanced',
    ];

    expect($composer)->toHaveKey('suggest');

    foreach ($suggestedPackages as $package) {
        expect($composer['suggest'])->toHaveKey($package);
    }
});
