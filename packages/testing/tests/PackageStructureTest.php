<?php

declare(strict_types=1);

it('has a valid composer.json with correct name, namespace, and dependencies', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/testing');
});

it('has PSR-4 autoloading configured for Marko\Testing namespace', function () {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Testing\\')
        ->and($composer['autoload']['psr-4']['Marko\\Testing\\'])->toBe('src/');
});

it(
    'requires interface packages as dependencies (core, config, mail, queue, session, log, authentication)',
    function () {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/core')
            ->and($composer['require'])->toHaveKey('marko/config')
            ->and($composer['require'])->toHaveKey('marko/mail')
            ->and($composer['require'])->toHaveKey('marko/queue')
            ->and($composer['require'])->toHaveKey('marko/session')
            ->and($composer['require'])->toHaveKey('marko/log')
            ->and($composer['require'])->toHaveKey('marko/authentication');
    },
);

it('has a module.php with correct module configuration', function () {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray();
});

it('has src directory for source code', function () {
    $srcPath = dirname(__DIR__) . '/src';

    expect(is_dir($srcPath))->toBeTrue();
});

it('has tests directory for tests', function () {
    $testsPath = dirname(__DIR__) . '/tests';

    expect(is_dir($testsPath))->toBeTrue();
});
