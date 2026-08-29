<?php

declare(strict_types=1);

namespace Marko\Tests\Support\Psr7Containment;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Discovers PHP source and test files across the monorepo's packages,
 * excluding a given package.
 *
 * Walks each package's `src/` and `tests/` directories with
 * `RecursiveDirectoryIterator` rather than `glob()`, since `glob()` has no
 * `**` support and would silently miss a file living directly under a
 * top-level directory with zero intermediate subdirectories.
 */
readonly class Psr7SymbolDiscovery
{
    /**
     * @return list<string>
     *
     * @throws UnexpectedValueException
     */
    public function discover(
        string $packagesRoot,
        string $excludingPackage,
    ): array
    {
        $files = [];

        foreach ($this->packageDirectories($packagesRoot, $excludingPackage) as $directory) {
            foreach ($this->phpFilesUnder($directory) as $file) {
                $files[] = $file;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function packageDirectories(
        string $packagesRoot,
        string $excludingPackage,
    ): array
    {
        $entries = scandir($packagesRoot);

        if ($entries === false) {
            return [];
        }

        $directories = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === $excludingPackage) {
                continue;
            }

            foreach (['src', 'tests'] as $subdirectory) {
                $path = $packagesRoot . '/' . $entry . '/' . $subdirectory;

                if (is_dir($path)) {
                    $directories[] = $path;
                }
            }
        }

        return $directories;
    }

    /**
     * @return list<string>
     *
     * @throws UnexpectedValueException
     */
    private function phpFilesUnder(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }
}
