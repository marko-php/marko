<?php

declare(strict_types=1);

it('ships a known-engines.php file in marko/view', function () {
    $path = dirname(__DIR__) . '/known-engines.php';

    expect(file_exists($path))->toBeTrue();
});

it('registers twig with extension .twig and driver marko/view-twig', function () {
    $engines = require dirname(__DIR__) . '/known-engines.php';

    expect($engines)->toHaveKey('twig')
        ->and($engines['twig']['extension'])->toBe('.twig')
        ->and($engines['twig']['driver'])->toBe('marko/view-twig');
});

it('registers latte with extension .latte and driver marko/view-latte', function () {
    $engines = require dirname(__DIR__) . '/known-engines.php';

    expect($engines)->toHaveKey('latte')
        ->and($engines['latte']['extension'])->toBe('.latte')
        ->and($engines['latte']['driver'])->toBe('marko/view-latte');
});

it('lists twig first as the recommended engine', function () {
    $engines = require dirname(__DIR__) . '/known-engines.php';

    expect(array_key_first($engines))->toBe('twig');
});

it('returns an array keyed by short engine name with extension and driver fields', function () {
    $engines = require dirname(__DIR__) . '/known-engines.php';

    expect($engines)->toBeArray();

    foreach ($engines as $name => $config) {
        expect($name)->toBeString()
            ->and($config)->toHaveKey('extension')
            ->and($config)->toHaveKey('driver');
    }
});

it('uses declare strict_types', function () {
    $contents = file_get_contents(dirname(__DIR__) . '/known-engines.php');

    expect($contents)->toContain('declare(strict_types=1)');
});
