<?php

declare(strict_types=1);

it('has valid composer.json with correct name', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/framework');
});

it('composer.json validates', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $content = file_get_contents($composerPath);
    $decoded = json_decode($content, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded)->toBeArray()
        ->and($decoded)->toHaveKey('name')
        ->and($decoded)->toHaveKey('type')
        ->and($decoded)->toHaveKey('require');
});

it('all required packages exist', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('require');

    foreach ($composer['require'] as $package => $version) {
        expect($package === 'php' || str_starts_with($package, 'marko/'))
            ->toBeTrue("Package '$package' must be 'php' or have 'marko/' prefix");
    }
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
    }
});

it('suggests optional packages', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $suggestedPackages = [
        'marko/database',
        'marko/cache',
        'marko/session',
        'marko/authentication',
        'marko/log',
        'marko/filesystem',
        'marko/errors-advanced',
    ];

    expect($composer)->toHaveKey('suggest');

    foreach ($suggestedPackages as $package) {
        expect($composer['suggest'])->toHaveKey($package);
    }
});

it('README exists in package root', function () {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();
});

it('README documents included packages', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    $includedPackages = [
        'marko/core',
        'marko/routing',
        'marko/cli',
        'marko/errors',
        'marko/errors-simple',
        'marko/config',
        'marko/hashing',
        'marko/validation',
    ];

    foreach ($includedPackages as $package) {
        expect($content)->toContain($package);
    }
});

it('README documents optional packages', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    $optionalPackages = [
        'marko/database',
        'marko/cache',
        'marko/session',
        'marko/authentication',
        'marko/log',
        'marko/filesystem',
        'marko/errors-advanced',
    ];

    foreach ($optionalPackages as $package) {
        expect($content)->toContain($package);
    }
});

it('README shows installation examples', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('composer require marko/framework');
});
