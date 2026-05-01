<?php

declare(strict_types=1);

test('inertia-vue config loads client and ssr entries', function () {
    $config = require dirname(__DIR__).'/config/inertia-vue.php';

    expect($config['clientEntry'])->toBe('app/vue-web/resources/js/app.js');
    expect($config['ssrEntry'])->toBe('app/vue-web/resources/js/ssr.js');
    expect($config['ssrBundle'])->toBe('bootstrap/ssr/vue/ssr.js');
});

test('inertia-vue is a config-only marko module', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(file_exists(dirname(__DIR__).'/module.php'))->toBeFalse()
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});
