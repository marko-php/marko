<?php

declare(strict_types=1);

test('it lists every driver from every known-drivers.php file in skeleton suggest', function (): void {
    $knownDriversFiles = glob(__DIR__ . '/../../*/known-drivers.php');
    $skeletonSuggest = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        associative: true,
    )['suggest'] ?? [];

    foreach ($knownDriversFiles as $knownDriversPath) {
        $drivers = require $knownDriversPath;
        foreach ($drivers as $package => $description) {
            expect($skeletonSuggest)->toHaveKey($package);
        }
    }
});

test('it preserves descriptions verbatim between known-drivers.php and skeleton suggest', function (): void {
    $knownDriversFiles = glob(__DIR__ . '/../../*/known-drivers.php');
    $skeletonSuggest = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        associative: true,
    )['suggest'] ?? [];

    foreach ($knownDriversFiles as $knownDriversPath) {
        $drivers = require $knownDriversPath;
        foreach ($drivers as $package => $description) {
            expect($skeletonSuggest)->toHaveKey($package)
                ->and($skeletonSuggest[$package])->toBe($description);
        }
    }
});

test('it includes marko/database-readwrite as an optional add-on', function (): void {
    $skeletonSuggest = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        associative: true,
    )['suggest'] ?? [];

    expect($skeletonSuggest)->toHaveKey('marko/database-readwrite')
        ->and($skeletonSuggest['marko/database-readwrite'])->toBe(
            'Read/write connection splitting decorator (optional — works alongside a base driver)'
        );
});

test('it includes marko/page-cache-entity as an optional add-on', function (): void {
    $skeletonSuggest = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        associative: true,
    )['suggest'] ?? [];

    expect($skeletonSuggest)->toHaveKey('marko/page-cache-entity')
        ->and($skeletonSuggest['marko/page-cache-entity'])->toBe(
            'Auto-purges page-cache tags on entity save/delete (optional add-on)'
        );
});

test('it does not move any view, database, cache, etc. drivers into require or require-dev', function (): void {
    $composer = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        associative: true,
    );

    $require = array_merge(
        array_keys($composer['require'] ?? []),
        array_keys($composer['require-dev'] ?? []),
    );

    $knownDriversFiles = glob(__DIR__ . '/../../*/known-drivers.php');

    foreach ($knownDriversFiles as $knownDriversPath) {
        $drivers = require $knownDriversPath;
        foreach ($drivers as $package => $description) {
            expect($require)->not->toContain($package);
        }
    }
});

test('skeleton composer.json remains valid JSON after the consolidation', function (): void {
    $composerPath = __DIR__ . '/../composer.json';
    $content = file_get_contents($composerPath);
    $composer = json_decode($content, associative: true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($composer['suggest'])->toBeArray();
});
