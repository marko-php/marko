<?php

declare(strict_types=1);

use Marko\Testing\KnownDrivers\KnownDriversValidator;
use PHPUnit\Framework\SkippedWithMessageException;

$knownDriversPath = __DIR__ . '/../known-drivers.php';
$skeletonComposerPath = __DIR__ . '/../../skeleton/composer.json';

test(
    'skeleton suggest block contains all validation drivers',
    function () use ($knownDriversPath, $skeletonComposerPath): void {
        KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath);
    },
);

test('every validation driver follows marko slash prefix pattern', function () use ($knownDriversPath): void {
    KnownDriversValidator::assertDocsUrlsResolveToValidPattern($knownDriversPath);
});

test(
    'validation test skips skeleton parity assertion when skeleton is absent',
    function () use ($knownDriversPath): void {
        $nonExistentPath = __DIR__ . '/non-existent-path/composer.json';

        expect(file_exists($nonExistentPath))->toBeFalse();

        $skipped = false;

        try {
            KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $nonExistentPath);
        } catch (SkippedWithMessageException $e) {
            $skipped = true;
            expect($e->getMessage())->toContain('not found');
        }

        expect($skipped)->toBeTrue();
    },
);
