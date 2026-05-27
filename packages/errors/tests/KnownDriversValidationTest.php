<?php

declare(strict_types=1);

use Marko\Testing\KnownDrivers\KnownDriversValidator;

$knownDriversPath = __DIR__ . '/../known-drivers.php';
$skeletonComposerPath = __DIR__ . '/../../skeleton/composer.json';

test('it ships a known-drivers.php file listing both errors drivers', function () use ($knownDriversPath): void {
    expect(file_exists($knownDriversPath))->toBeTrue();

    $drivers = require $knownDriversPath;

    expect($drivers)->toBeArray()
        ->and(array_key_exists('marko/errors-simple', $drivers))->toBeTrue()
        ->and(array_key_exists('marko/errors-advanced', $drivers))->toBeTrue();
});

test('it lists marko/errors-simple first as the recommended driver', function () use ($knownDriversPath): void {
    $drivers = require $knownDriversPath;

    expect(array_key_first($drivers))->toBe('marko/errors-simple');
});

test(
    'skeleton suggest block contains all errors drivers',
    function () use ($knownDriversPath, $skeletonComposerPath): void {
        KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath);
    }
);

test('every errors driver follows marko slash prefix pattern', function () use ($knownDriversPath): void {
    KnownDriversValidator::assertDocsUrlsResolveToValidPattern($knownDriversPath);
});
