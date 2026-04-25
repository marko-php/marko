<?php

declare(strict_types=1);

test('inertia-react config loads client and ssr entries', function () {
    $config = require dirname(__DIR__).'/config/inertia-react.php';

    expect($config['clientEntry'])->toBe('app/react-web/resources/js/app.jsx');
    expect($config['ssrEntry'])->toBe('app/react-web/resources/js/ssr.jsx');
    expect($config['ssrBundle'])->toEndWith('/bootstrap/ssr/react/ssr.js');
});

test('inertia-react module depends on inertia and vite', function () {
    $module = require dirname(__DIR__).'/module.php';

    expect($module['enabled'])->toBeTrue();
    expect($module['sequence']['after'])->toContain('marko/inertia');
    expect($module['sequence']['after'])->toContain('marko/vite');
});
