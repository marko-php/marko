<?php

declare(strict_types=1);

test('inertia-react config loads client and ssr entries', function () {
    $config = require dirname(__DIR__).'/config/inertia-react.php';

    expect($config['clientEntry'])->toBe('app/react-web/resources/js/app.jsx');
    expect($config['ssrEntry'])->toBe('app/react-web/resources/js/ssr.jsx');
    expect($config['ssrBundle'])->toBe('bootstrap/ssr/react/ssr.js');
});

test('inertia-react is a config-only marko module', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(file_exists(dirname(__DIR__).'/module.php'))->toBeFalse()
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});
