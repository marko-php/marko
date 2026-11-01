<?php

declare(strict_types=1);

use Marko\Testing\KnownDrivers\KnownDriversValidator;

$knownDriversPath = __DIR__ . '/../known-drivers.php';
$skeletonComposerPath = __DIR__ . '/../../skeleton/composer.json';

test('it ships a known-drivers.php file listing all four queue drivers', function () use ($knownDriversPath): void {
    expect(file_exists($knownDriversPath))->toBeTrue();

    $drivers = require $knownDriversPath;

    expect($drivers)->toBeArray()
        ->and(array_keys($drivers))->toContain('marko/queue-sync')
        ->and(array_keys($drivers))->toContain('marko/queue-database')
        ->and(array_keys($drivers))->toContain('marko/queue-rabbitmq')
        ->and(array_keys($drivers))->toContain('marko/queue-redis')
        ->and($drivers)->toHaveCount(4);
});

test(
    'it lists marko/queue-sync first as the recommended development default',
    function () use ($knownDriversPath): void {
        $drivers = require $knownDriversPath;

        expect(array_key_first($drivers))->toBe('marko/queue-sync');
    },
);

test(
    'skeleton suggest block contains all queue drivers',
    fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath),
);

test(
    'every queue driver follows marko slash prefix pattern',
    fn () => KnownDriversValidator::assertDocsUrlsResolveToValidPattern($knownDriversPath),
);
