<?php

declare(strict_types=1);

use Marko\Config\ConfigMerger;
use Marko\Config\RemoveKey;

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

it('keeps a key whose override value is null', function () {
    $merger = new ConfigMerger();

    $result = $merger->merge(['a' => 1, 'b' => 2], ['a' => null]);

    expect($result)->toBe(['a' => null, 'b' => 2]);
});

it('keeps a nested key whose override value is null', function () {
    $merger = new ConfigMerger();

    $result = $merger->merge(['x' => ['y' => 1, 'z' => 2]], ['x' => ['y' => null]]);

    expect($result)->toBe(['x' => ['y' => null, 'z' => 2]]);
});

it('keeps a null key from a package default overridden by the same null', function () {
    $merger = new ConfigMerger();

    $package = ['remember' => ['cookie' => ['secure' => null, 'path' => '/']]];
    $app = ['remember' => ['cookie' => ['secure' => null, 'path' => '/']]];

    expect($merger->merge($package, $app))->toBe($package);
});

it('keeps top-level null keys with mergeAll', function () {
    $merger = new ConfigMerger();

    expect($merger->mergeAll(['a' => null]))->toBe(['a' => null])
        ->and($merger->mergeAll(['a' => 1], ['a' => null]))->toBe(['a' => null]);
});

it('replaces a null base value with an array override', function () {
    $merger = new ConfigMerger();

    $result = $merger->merge(['tls' => null], ['tls' => ['verify_peer' => true]]);

    expect($result)->toBe(['tls' => ['verify_peer' => true]]);
});

it('removes a key whose override value is RemoveKey', function () {
    $merger = new ConfigMerger();

    $base = ['name' => 'original', 'debug' => true, 'nested' => ['keep' => 1, 'drop' => 2]];
    $override = ['debug' => new RemoveKey(), 'nested' => ['drop' => new RemoveKey()]];

    expect($merger->merge($base, $override))->toBe(['name' => 'original', 'nested' => ['keep' => 1]]);
});

it('drops RemoveKey markers for keys the base does not have', function () {
    $merger = new ConfigMerger();

    $result = $merger->merge(
        ['a' => 1],
        ['missing' => new RemoveKey(), 'fresh' => ['keep' => 1, 'drop' => new RemoveKey(), 'deep' => ['drop' => new RemoveKey()]]],
    );

    expect($result)->toBe(['a' => 1, 'fresh' => ['keep' => 1, 'deep' => []]])
        ->and($merger->mergeAll(['only' => new RemoveKey(), 'b' => 2]))->toBe(['b' => 2]);
});

it('drops RemoveKey markers inside an indexed array override', function () {
    $merger = new ConfigMerger();

    $result = $merger->merge(['items' => ['a', 'b']], ['items' => ['c', new RemoveKey(), 'd']]);

    expect($result)->toBe(['items' => ['c', 'd']]);
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
