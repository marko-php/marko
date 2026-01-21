<?php

declare(strict_types=1);

namespace Marko\Database\Discovery;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Common methods for discovering PHP classes in directories.
 */
trait PhpFileDiscoveryTrait
{
    /**
     * Extract the fully qualified class name from a PHP file.
     */
    private function extractClassName(
        string $filePath,
    ): ?string {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }

    /**
     * Find all PHP files in a directory recursively.
     *
     * @return iterable<SplFileInfo>
     */
    private function findPhpFiles(
        string $directory,
    ): iterable {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            yield $file;
        }
    }
}
