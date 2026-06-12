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

        $tokens = token_get_all($contents);
        $namespace = null;
        $typeName = null;
        $prevSignificant = null;
        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                $prevSignificant = $token;
                continue;
            }

            [$id] = $token;

            // Skip whitespace and comments for tracking purposes
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($id === T_NAMESPACE) {
                // Consume namespace name tokens until ';' or '{'
                $namespaceParts = [];
                for ($j = $i + 1; $j < $tokenCount; $j++) {
                    $t = $tokens[$j];
                    if (!is_array($t)) {
                        // ';' or '{' ends the namespace
                        break;
                    }
                    [$tid, $tval] = $t;
                    if (in_array($tid, [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $namespaceParts[] = $tval;
                    } elseif ($tid !== T_WHITESPACE) {
                        break;
                    }
                }
                $namespace = implode('', $namespaceParts);
                $prevSignificant = $token;
                continue;
            }

            if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                // Skip ::class constant references
                if ($prevSignificant === '::' || (is_array(
                    $prevSignificant,
                ) && $prevSignificant[0] === T_DOUBLE_COLON)) {
                    $prevSignificant = $token;
                    continue;
                }

                // Skip anonymous classes (new class)
                if (is_array($prevSignificant) && $prevSignificant[0] === T_NEW) {
                    $prevSignificant = $token;
                    continue;
                }

                // Find the name token (T_STRING) after the type keyword, skipping whitespace
                for ($j = $i + 1; $j < $tokenCount; $j++) {
                    $t = $tokens[$j];
                    if (!is_array($t)) {
                        break;
                    }
                    [$tid, $tval] = $t;
                    if ($tid === T_WHITESPACE) {
                        continue;
                    }
                    if ($tid === T_STRING) {
                        $typeName = $tval;
                    }
                    break;
                }

                if ($typeName !== null) {
                    break;
                }
            }

            $prevSignificant = $token;
        }

        if ($typeName === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $typeName : $typeName;
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
