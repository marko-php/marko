<?php

declare(strict_types=1);

use Marko\Config\ConfigMerger;

it('overrides scalar values when merging', function () {
    $merger = new ConfigMerger();

    $base = ['name' => 'original', 'count' => 5];
    $override = ['name' => 'overridden'];

    $result = $merger->merge($base, $override);

    expect($result)->toBe(['name' => 'overridden', 'count' => 5]);
});

it('replaces indexed arrays entirely when merging', function () {
    $merger = new ConfigMerger();

    $base = ['items' => ['apple', 'banana', 'cherry']];
    $override = ['items' => ['orange', 'grape']];

    $result = $merger->merge($base, $override);

    expect($result)->toBe(['items' => ['orange', 'grape']]);
});

it('recursively merges associative arrays', function () {
    $merger = new ConfigMerger();

    $base = [
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'mydb',
        ],
    ];
    $override = [
        'database' => [
            'host' => 'production.server.com',
        ],
    ];

    $result = $merger->merge($base, $override);

    expect($result)->toBe([
        'database' => [
            'host' => 'production.server.com',
            'port' => 3306,
            'name' => 'mydb',
        ],
    ]);
});

it('removes key when override value is null', function () {
    $merger = new ConfigMerger();

    $base = ['name' => 'original', 'debug' => true, 'count' => 5];
    $override = ['debug' => null];

    $result = $merger->merge($base, $override);

    expect($result)->toBe(['name' => 'original', 'count' => 5]);
});

it('merges multiple arrays with mergeAll', function () {
    $merger = new ConfigMerger();

    $first = ['a' => 1, 'b' => 2];
    $second = ['b' => 3, 'c' => 4];
    $third = ['c' => 5, 'd' => 6];

    $result = $merger->mergeAll($first, $second, $third);

    expect($result)->toBe(['a' => 1, 'b' => 3, 'c' => 5, 'd' => 6]);
});

it('merges deeply nested associative arrays', function () {
    $merger = new ConfigMerger();

    $base = [
        'level1' => [
            'level2' => [
                'level3' => [
                    'a' => 1,
                    'b' => 2,
                ],
            ],
        ],
    ];
    $override = [
        'level1' => [
            'level2' => [
                'level3' => [
                    'b' => 3,
                    'c' => 4,
                ],
            ],
        ],
    ];

    $result = $merger->merge($base, $override);

    expect($result)->toBe([
        'level1' => [
            'level2' => [
                'level3' => [
                    'a' => 1,
                    'b' => 3,
                    'c' => 4,
                ],
            ],
        ],
    ]);
});

it('handles mixed indexed and associative arrays correctly', function () {
    $merger = new ConfigMerger();

    $base = [
        'settings' => [
            'debug' => true,
            'items' => ['one', 'two', 'three'],
        ],
    ];
    $override = [
        'settings' => [
            'debug' => false,
            'items' => ['four', 'five'],
        ],
    ];

    $result = $merger->merge($base, $override);

    expect($result)->toBe([
        'settings' => [
            'debug' => false,
            'items' => ['four', 'five'],
        ],
    ]);
});

it('handles empty arrays in merge', function () {
    $merger = new ConfigMerger();

    expect($merger->merge([], ['a' => 1]))->toBe(['a' => 1])
        ->and($merger->merge(['a' => 1], []))->toBe(['a' => 1])
        ->and($merger->merge([], []))->toBe([]);
});

it('handles empty arrays in mergeAll', function () {
    $merger = new ConfigMerger();

    expect($merger->mergeAll())->toBe([])
        ->and($merger->mergeAll([]))->toBe([])
        ->and($merger->mergeAll([], [], []))->toBe([])
        ->and($merger->mergeAll([], ['a' => 1], []))->toBe(['a' => 1]);
});
