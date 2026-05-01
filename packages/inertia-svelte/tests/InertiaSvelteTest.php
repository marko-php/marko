<?php

declare(strict_types=1);

use Marko\Inertia\Frontend\InertiaFrontendInterface;
use Marko\Inertia\Svelte\SvelteInertiaFrontend;

test('inertia-svelte config overlays the parent inertia config', function () {
    $config = require dirname(__DIR__).'/config/inertia.php';

    expect($config['assetEntry'])->toBe('app/svelte-web/resources/js/app.js');
    expect($config['ssr']['bundle'])->toBe('bootstrap/ssr/svelte/ssr.js');
    expect($config)->not->toHaveKey('ssrEntry');
});

test('inertia-svelte binds the inertia frontend marker', function () {
    $module = require dirname(__DIR__).'/module.php';

    expect($module['bindings'])->toHaveKey(InertiaFrontendInterface::class)
        ->and($module['bindings'][InertiaFrontendInterface::class])->toBe(SvelteInertiaFrontend::class);
});

test('svelte inertia frontend identifies itself', function () {
    expect((new SvelteInertiaFrontend())->name())->toBe('svelte');
});

test('inertia-svelte is a marko module', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(file_exists(dirname(__DIR__).'/module.php'))->toBeTrue()
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Inertia\\Svelte\\');
});
