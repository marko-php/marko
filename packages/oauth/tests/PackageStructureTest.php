<?php

declare(strict_types=1);

it('has valid composer metadata for marko/oauth', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    expect($composer)->toBeArray()
        ->and($composer['name'])->toBe('marko/oauth')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require'])->toHaveKey('league/oauth2-server')
        ->and($composer['require'])->toHaveKey('marko/authentication')
        ->and($composer['require'])->toHaveKey('marko/database')
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\OAuth\\')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\OAuth\\Tests\\');
});

it('has package distribution metadata', function (): void {
    $root = dirname(__DIR__);

    expect(file_exists($root . '/LICENSE'))->toBeTrue()
        ->and(file_get_contents($root . '/LICENSE'))->toContain('MIT License')
        ->and(file_exists($root . '/.gitattributes'))->toBeTrue()
        ->and(file_get_contents($root . '/.gitattributes'))->toContain('/tests')
        ->and(file_get_contents($root . '/.gitattributes'))->toContain('export-ignore');
});

it('provides module configuration', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

it('provides default oauth configuration', function (): void {
    $config = require dirname(__DIR__) . '/config/oauth.php';

    expect($config)->toHaveKeys([
        'routes',
        'keys',
        'tokens',
        'refresh_tokens',
        'consent',
        'scopes',
        'default_scopes',
    ])
        ->and($config['routes']['prefix'])->toBe('/oauth')
        ->and($config['routes']['enabled'])->toBeTrue()
        ->and($config['routes']['management'])->toBeFalse();
});
