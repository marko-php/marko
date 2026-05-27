<?php

declare(strict_types=1);

use Marko\Testing\KnownDrivers\KnownDriversValidator;

$knownDriversPath = __DIR__ . '/../known-drivers.php';
$skeletonComposerPath = __DIR__ . '/../../skeleton/composer.json';

test('skeleton suggest block contains all translation drivers', fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath));
test('every translation driver follows marko slash prefix pattern', fn () => KnownDriversValidator::assertDocsUrlsResolveToValidPattern($knownDriversPath));
