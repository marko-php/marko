<?php

declare(strict_types=1);

use Marko\CodeIndexer\Module\ModuleWalker;
use Marko\CodeIndexer\ValueObject\ModuleInfo;

$fixtures = __DIR__ . '/../../Fixtures/MiniMonorepo';

it('discovers vendor modules two levels deep', function () use ($fixtures): void {
    $walker = new ModuleWalker($fixtures);

    $results = $walker->walk();
    $names = array_map(fn (ModuleInfo $m): string => $m->name, $results);

    expect(in_array('foo/bar', $names, true))->toBeTrue()
        ->and(in_array('foo/baz', $names, true))->toBeTrue();
});

it('discovers app modules one level deep', function () use ($fixtures): void {
    $walker = new ModuleWalker($fixtures);

    $results = $walker->walk();
    $names = array_map(fn (ModuleInfo $m): string => $m->name, $results);

    expect(in_array('app/myapp', $names, true))->toBeTrue();
});

it('discovers modules directory recursively', function () use ($fixtures): void {
    $walker = new ModuleWalker($fixtures);

    $results = $walker->walk();
    $names = array_map(fn (ModuleInfo $m): string => $m->name, $results);

    expect(in_array('local/level1', $names, true))->toBeTrue()
        ->and(in_array('local/deep', $names, true))->toBeTrue();
});

it('returns empty array when no modules exist', function (): void {
    $emptyRoot = sys_get_temp_dir() . '/marko-walker-empty-' . uniqid();
    mkdir($emptyRoot, 0777, true);

    $walker = new ModuleWalker($emptyRoot);

    expect($walker->walk())->toBe([]);

    rmdir($emptyRoot);
});

it('returns ModuleInfo with correctly parsed composer.json name', function () use ($fixtures): void {
    $walker = new ModuleWalker($fixtures);

    $results = $walker->walk();
    $barInfo = null;
    foreach ($results as $info) {
        if ($info->name === 'foo/bar') {
            $barInfo = $info;
            break;
        }
    }

    expect($barInfo)->not->toBeNull()
        ->and($barInfo->name)->toBe('foo/bar')
        ->and($barInfo->path)->toBe($fixtures . '/vendor/foo/bar')
        ->and($barInfo->namespace)->toBe('Foo\\Bar\\');
});

it('returns ModuleInfo with module.php manifest when file exists', function () use ($fixtures): void {
    $walker = new ModuleWalker($fixtures);

    $results = $walker->walk();
    $barInfo = null;
    foreach ($results as $info) {
        if ($info->name === 'foo/bar') {
            $barInfo = $info;
            break;
        }
    }

    expect($barInfo)->not->toBeNull()
        ->and($barInfo->manifest)->toBeArray()
        ->and($barInfo->manifest)->toHaveKey('bindings')
        ->and($barInfo->manifest)->toHaveKey('singletons')
        ->and($barInfo->manifest['singletons'])->toBe(['FooBarService']);
});

it('returns ModuleInfo with empty manifest when module.php is absent', function () use ($fixtures): void {
    $walker = new ModuleWalker($fixtures);

    $results = $walker->walk();
    $bazInfo = null;
    foreach ($results as $info) {
        if ($info->name === 'foo/baz') {
            $bazInfo = $info;
            break;
        }
    }

    expect($bazInfo)->not->toBeNull()
        ->and($bazInfo->manifest)->toBe([]);
});

it('respects override priority app over modules over vendor when duplicates exist', function () use ($fixtures): void {
    $walker = new ModuleWalker($fixtures);

    $results = $walker->walk();

    // 'acme/duplicate' exists in both vendor/acme/duplicate and app/duplicate
    // The app version should win, so path should be the app path
    $duplicateInfo = null;
    foreach ($results as $info) {
        if ($info->name === 'acme/duplicate') {
            $duplicateInfo = $info;
            break;
        }
    }

    expect($duplicateInfo)->not->toBeNull()
        ->and($duplicateInfo->path)->toBe($fixtures . '/app/duplicate');

    // Confirm only one entry for the duplicate name
    $duplicateCount = count(array_filter(
        $results,
        fn (ModuleInfo $m): bool => $m->name === 'acme/duplicate',
    ));
    expect($duplicateCount)->toBe(1);
});
