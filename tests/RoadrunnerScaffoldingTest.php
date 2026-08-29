<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);

it('is registered as a path repository in the root composer json', function () use ($rootPath): void {
    $composer = json_decode(file_get_contents($rootPath . '/composer.json'), true);

    $paths = array_column($composer['repositories'], 'url');

    expect($paths)->toContain('packages/roadrunner');
});

it('is included in the phpstan analysis paths', function () use ($rootPath): void {
    $phpstan = file_get_contents($rootPath . '/phpstan.neon');

    expect($phpstan)->toContain('packages/roadrunner/src');
});

it('is required by the root composer json', function () use ($rootPath): void {
    $composer = json_decode(file_get_contents($rootPath . '/composer.json'), true);

    expect($composer['require'])->toHaveKey('marko/roadrunner')
        ->and($composer['require']['marko/roadrunner'])->toBe('self.version');
});

it('maps the package test namespace in root autoload dev', function () use ($rootPath): void {
    $composer = json_decode(file_get_contents($rootPath . '/composer.json'), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Roadrunner\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Roadrunner\\Tests\\'])->toBe('packages/roadrunner/tests/');
});

it('declares the roadrunner and psr7 dependencies in the root require dev', function () use ($rootPath): void {
    $composer = json_decode(file_get_contents($rootPath . '/composer.json'), true);

    expect($composer['require-dev'])->toHaveKey('spiral/roadrunner-http')
        ->and($composer['require-dev'])->toHaveKey('nyholm/psr7');
});
