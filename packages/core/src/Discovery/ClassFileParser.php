<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Error;
use Marko\Core\Exceptions\MarkoException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Utility for parsing PHP files to extract class information.
 *
 * Used by discovery mechanisms (observers, routes, preferences, etc.)
 * to find and identify classes in module directories.
 */
class ClassFileParser
{
    /**
     * Extract the fully qualified class name from a PHP file.
     */
    public function extractClassName(
        string $filePath,
    ): ?string {
        if (!is_file($filePath)) {
            return null;
        }

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
     * Load a class file and verify the class exists.
     *
     * Handles missing Marko package dependencies gracefully by returning false
     * (the file depends on an uninstalled optional package). Non-Marko missing
     * classes are re-thrown as real errors.
     *
     * @return bool True if the class was loaded successfully, false if skipped
     */
    public function loadClass(
        string $filePath,
        string $className,
    ): bool {
        if (class_exists($className, false)) {
            return true;
        }

        try {
            require_once $filePath;

            if (!class_exists($className)) {
                return false;
            }
        } catch (Error $e) {
            $missingClass = MarkoException::extractMissingClass($e);
            if ($missingClass !== null && MarkoException::inferPackageName($missingClass) !== null) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    /**
     * Find all PHP files in a directory recursively.
     *
     * @return iterable<SplFileInfo>
     */
    public function findPhpFiles(
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
