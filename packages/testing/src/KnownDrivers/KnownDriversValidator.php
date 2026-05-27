<?php

declare(strict_types=1);

namespace Marko\Testing\KnownDrivers;

use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SkippedWithMessageException;

class KnownDriversValidator
{
    /**
     * @return array<string, string>
     *
     * @throws InvalidArgumentException
     */
    private static function readKnownDrivers(string $knownDriversPath): array
    {
        if (! file_exists($knownDriversPath)) {
            throw new InvalidArgumentException(
                "known-drivers.php not found at: $knownDriversPath",
            );
        }

        /** @var array<string, string> */
        return require $knownDriversPath;
    }

    /**
     * @throws InvalidArgumentException
     * @throws AssertionFailedError
     */
    public static function assertDocsUrlsResolveToValidPattern(string $knownDriversPath): void
    {
        $drivers = self::readKnownDrivers($knownDriversPath);

        foreach (array_keys($drivers) as $packageName) {
            if (! str_starts_with($packageName, 'marko/')) {
                throw new AssertionFailedError(
                    "Driver key '$packageName' does not follow the 'marko/*' prefix pattern.",
                );
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws SkippedWithMessageException
     * @throws AssertionFailedError
     */
    public static function assertSkeletonSuggestContainsAll(
        string $knownDriversPath,
        string $skeletonComposerPath,
    ): void {
        if (! file_exists($skeletonComposerPath)) {
            throw new SkippedWithMessageException(
                "Skeleton composer.json not found at: $skeletonComposerPath",
            );
        }

        $composerJson = json_decode((string) file_get_contents($skeletonComposerPath), true);

        if (! isset($composerJson['suggest'])) {
            throw new SkippedWithMessageException(
                'Skeleton composer.json has no suggest key yet — skipping until task 024 populates it.',
            );
        }

        /** @var array<string, string> $suggest */
        $suggest = $composerJson['suggest'];
        $drivers = self::readKnownDrivers($knownDriversPath);

        foreach ($drivers as $packageName => $description) {
            if (! array_key_exists($packageName, $suggest)) {
                throw new AssertionFailedError(
                    "Skeleton suggest is missing known driver '$packageName'.",
                );
            }

            if ($suggest[$packageName] !== $description) {
                throw new AssertionFailedError(
                    "Skeleton suggest entry for '$packageName' has description '{$suggest[$packageName]}' but expected '$description'.",
                );
            }
        }
    }
}
