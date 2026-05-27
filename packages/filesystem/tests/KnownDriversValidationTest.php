<?php

declare(strict_types=1);

use Marko\Testing\KnownDrivers\KnownDriversValidator;

$knownDriversPath = __DIR__ . '/../known-drivers.php';
$skeletonComposerPath = __DIR__ . '/../../skeleton/composer.json';

test('skeleton suggest block contains all filesystem drivers', function () use ($knownDriversPath, $skeletonComposerPath) {
    KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath);
});

test('every filesystem driver follows marko slash prefix pattern', function () use ($knownDriversPath) {
    KnownDriversValidator::assertDocsUrlsResolveToValidPattern($knownDriversPath);
});
