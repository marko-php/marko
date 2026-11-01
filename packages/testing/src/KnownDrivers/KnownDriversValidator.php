<?php

declare(strict_types=1);

namespace Marko\Testing\KnownDrivers;

use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
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
     * @throws InvalidArgumentException|ExpectationFailedException
     */
    public static function assertDocsUrlsResolveToValidPattern(string $knownDriversPath): void
    {
        $drivers = self::readKnownDrivers($knownDriversPath);

        foreach (array_keys($drivers) as $packageName) {
            Assert::assertStringStartsWith(
                'marko/',
                $packageName,
                "Driver key '$packageName' does not follow the 'marko/*' prefix pattern.",
            );
        }
    }

    /**
     * @throws InvalidArgumentException|SkippedWithMessageException|ExpectationFailedException
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
        /** @var array<string, string> $require */
        $require = $composerJson['require'] ?? [];
        $drivers = self::readKnownDrivers($knownDriversPath);

        foreach ($drivers as $packageName => $description) {
            // A driver the skeleton ships is covered by require; suggesting it too would do nothing.
            if (array_key_exists($packageName, $require)) {
                Assert::assertArrayNotHasKey(
                    $packageName,
                    $suggest,
                    "Skeleton requires known driver '$packageName'; remove its redundant suggest entry.",
                );

                continue;
            }

            Assert::assertArrayHasKey(
                $packageName,
                $suggest,
                "Skeleton suggest is missing known driver '$packageName'.",
            );
            Assert::assertSame(
                $description,
                $suggest[$packageName],
                "Skeleton suggest entry for '$packageName' has description '{$suggest[$packageName]}' but expected '$description'.",
            );
        }
    }
}
