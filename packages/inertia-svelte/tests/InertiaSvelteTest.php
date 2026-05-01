<?php

declare(strict_types=1);

test('inertia-svelte config loads client and ssr entries', function () {
    $config = require dirname(__DIR__).'/config/inertia-svelte.php';

    expect($config['clientEntry'])->toBe('app/svelte-web/resources/js/app.js');
    expect($config['ssrEntry'])->toBe('app/svelte-web/resources/js/ssr.js');
    expect($config['ssrBundle'])->toBe('bootstrap/ssr/svelte/ssr.js');
});

test('inertia-svelte is a config-only marko module', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(file_exists(dirname(__DIR__).'/module.php'))->toBeFalse()
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});
