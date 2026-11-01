<?php

declare(strict_types=1);

use Marko\Testing\KnownDrivers\KnownDriversValidator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SkippedWithMessageException;

it('fails loudly when the known-drivers.php file itself is missing', function (): void {
    $missingPath = __DIR__ . '/fixtures/does-not-exist.php';

    expect(fn () => KnownDriversValidator::assertDocsUrlsResolveToValidPattern($missingPath))
        ->toThrow(InvalidArgumentException::class, 'known-drivers.php not found');
});

it('asserts every known driver follows marko slash prefix pattern', function (): void {
    $validPath = __DIR__ . '/fixtures/known-drivers.php';
    $invalidPath = __DIR__ . '/fixtures/known-drivers-invalid-prefix.php';

    expect(fn () => KnownDriversValidator::assertDocsUrlsResolveToValidPattern($validPath))
        ->not->toThrow(Throwable::class)
        ->and(fn () => KnownDriversValidator::assertDocsUrlsResolveToValidPattern($invalidPath))
        ->toThrow(AssertionFailedError::class, 'vendor/invalid-package');
});

it('allows skeleton suggest to contain entries beyond the known drivers list', function (): void {
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';
    $skeletonComposerPath = __DIR__ . '/fixtures/skeleton-with-extra-suggest-composer.json';

    expect(fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath))
        ->not->toThrow(Throwable::class);
});

it('fails skeleton assertion when skeleton has a suggest entry but description does not match', function (): void {
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';
    $skeletonComposerPath = __DIR__ . '/fixtures/skeleton-wrong-description-composer.json';

    expect(fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath))
        ->toThrow(AssertionFailedError::class, 'marko/cache-redis');
});

it('fails skeleton assertion when skeleton has a suggest key but is missing a known driver entry', function (): void {
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';
    $skeletonComposerPath = __DIR__ . '/fixtures/skeleton-missing-driver-composer.json';

    expect(fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath))
        ->toThrow(AssertionFailedError::class, "missing known driver 'marko/cache-memcached'");
});

it('counts a known driver the skeleton requires as covered without a suggest entry', function (): void {
    // Suggesting a package the skeleton already requires does nothing, so a
    // shipped driver is listed in require only.
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';
    $skeletonComposerPath = __DIR__ . '/fixtures/skeleton-with-required-driver-composer.json';

    expect(fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath))
        ->not->toThrow(Throwable::class);
});

it('fails skeleton assertion when a required known driver is also suggested', function (): void {
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';
    $skeletonComposerPath = __DIR__ . '/fixtures/skeleton-required-and-suggested-driver-composer.json';

    expect(fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath))
        ->toThrow(AssertionFailedError::class, 'remove its redundant suggest entry');
});

it('skips skeleton assertion when skeleton composer.json has no suggest key yet', function (): void {
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';
    $skeletonComposerPath = __DIR__ . '/fixtures/skeleton-without-suggest-composer.json';

    expect(fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath))
        ->toThrow(SkippedWithMessageException::class);
});

it('skips skeleton assertion gracefully when skeleton composer.json is not on disk', function (): void {
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';
    $skeletonComposerPath = __DIR__ . '/fixtures/non-existent-composer.json';

    expect(fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath))
        ->toThrow(SkippedWithMessageException::class);
});

it('asserts skeleton suggest block contains all known drivers with matching descriptions', function (): void {
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';
    $skeletonComposerPath = __DIR__ . '/fixtures/skeleton-with-suggest-composer.json';

    expect(fn () => KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath))
        ->not->toThrow(Throwable::class);
});

it('counts an assertion for every driver it checks against the prefix pattern', function (): void {
    $drivers = require __DIR__ . '/fixtures/known-drivers.php';
    $before = Assert::getCount();

    KnownDriversValidator::assertDocsUrlsResolveToValidPattern(__DIR__ . '/fixtures/known-drivers.php');

    expect(Assert::getCount() - $before)->toBeGreaterThanOrEqual(count($drivers));
});

it('counts assertions for every driver it checks against the skeleton suggest block', function (): void {
    $drivers = require __DIR__ . '/fixtures/known-drivers.php';
    $before = Assert::getCount();

    KnownDriversValidator::assertSkeletonSuggestContainsAll(
        __DIR__ . '/fixtures/known-drivers.php',
        __DIR__ . '/fixtures/skeleton-with-suggest-composer.json',
    );

    expect(Assert::getCount() - $before)->toBeGreaterThanOrEqual(count($drivers) * 2);
});

it('reads driver list from known-drivers.php file', function (): void {
    $knownDriversPath = __DIR__ . '/fixtures/known-drivers.php';

    // Should not throw — verifies the file is read successfully
    expect(fn () => KnownDriversValidator::assertDocsUrlsResolveToValidPattern($knownDriversPath))
        ->not->toThrow(Throwable::class);
});
