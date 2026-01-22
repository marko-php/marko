<?php

declare(strict_types=1);

it('composer.json exists with correct namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/view')
        ->and(json_decode(file_get_contents($composerPath), true)['autoload']['psr-4']['Marko\\View\\'])->toBe('src/');
});

it('composer.json has marko/core dependency', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('require')
        ->and($composer['require'])->toHaveKey('marko/core');
});

it('module.php exists with enabled status', function () {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('enabled')
        ->and($module['enabled'])->toBeTrue();
});
