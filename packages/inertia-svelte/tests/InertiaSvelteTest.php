<?php

declare(strict_types=1);

test('inertia-svelte config loads client and ssr entries', function () {
    $config = require dirname(__DIR__).'/config/inertia-svelte.php';

    expect($config['clientEntry'])->toBe('app/svelte-web/resources/js/app.js');
    expect($config['ssrEntry'])->toBe('app/svelte-web/resources/js/ssr.js');
    expect($config['ssrBundle'])->toEndWith('/bootstrap/ssr/svelte/ssr.js');
});

test('inertia-svelte module depends on inertia and vite', function () {
    $module = require dirname(__DIR__).'/module.php';

    expect($module['enabled'])->toBeTrue();
    expect($module['sequence']['after'])->toContain('marko/inertia');
    expect($module['sequence']['after'])->toContain('marko/vite');
});
