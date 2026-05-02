<?php

declare(strict_types=1);

use Marko\Inertia\Frontend\InertiaFrontendInterface;
use Marko\Inertia\React\ReactInertiaFrontend;

test('inertia-react config overlays the parent inertia config', function () {
    $config = require dirname(__DIR__).'/config/inertia.php';

    expect($config['assetEntry'])->toBe('app/react-web/resources/js/app.jsx');
    expect($config)->not->toHaveKey('ssrEntry');
});

test('inertia-react binds the inertia frontend marker', function () {
    $module = require dirname(__DIR__).'/module.php';

    expect($module['bindings'])->toHaveKey(InertiaFrontendInterface::class)
        ->and($module['bindings'][InertiaFrontendInterface::class])->toBe(ReactInertiaFrontend::class);
});

test('react inertia frontend identifies itself', function () {
    expect((new ReactInertiaFrontend())->name())->toBe('react');
});

test('inertia-react is a marko module', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(file_exists(dirname(__DIR__).'/module.php'))->toBeTrue()
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Inertia\\React\\');
});
