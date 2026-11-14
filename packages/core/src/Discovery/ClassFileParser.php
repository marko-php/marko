<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Error;
use Marko\Core\Environment\AppEnvironment;
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
    /** @var array<string, DiscoverySkip> skips this parser recorded, keyed by file path */
    private array $skippedFiles = [];

    public function __construct(
        private readonly AppEnvironment $appEnvironment = new AppEnvironment(),
    ) {}

    /**
     * Extract the fully qualified class name from a PHP file.
     *
     * Returns the first class, interface, trait or enum the file declares.
     */
    public function extractClassName(
        string $filePath,
    ): ?string {
        return $this->extractClassNames($filePath)[0] ?? null;
    }

    /**
     * Extract the fully qualified name of every class, interface, trait and enum a PHP file declares.
     *
     * Each name is qualified with the namespace in effect where it is declared, so a file with
     * several (bracketed) namespaces is handled. Anonymous classes and ::class references are skipped.
     *
     * @return array<int, string>
     */
    public function extractClassNames(
        string $filePath,
    ): array {
        if (!is_file($filePath)) {
            return [];
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return [];
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $classNames = [];
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
                $namespace = $this->readNamespace($tokens, $i + 1);
                $prevSignificant = $token;
                continue;
            }

            if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                if ($this->isClassReference($prevSignificant)) {
                    $prevSignificant = $token;
                    continue;
                }

                $typeName = $this->readTypeName($tokens, $i + 1);

                if ($typeName !== null) {
                    $classNames[] = $namespace !== '' ? $namespace . '\\' . $typeName : $typeName;
                }
            }

            $prevSignificant = $token;
        }

        return $classNames;
    }

    /**
     * Read a namespace name, starting just after the namespace keyword, until ';' or '{'.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readNamespace(
        array $tokens,
        int $start,
    ): string {
        $namespaceParts = [];
        $tokenCount = count($tokens);

        for ($j = $start; $j < $tokenCount; $j++) {
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

        return implode('', $namespaceParts);
    }

    /**
     * Whether a class-like keyword is a ::class constant reference or an anonymous class (new class).
     *
     * @param array{0: int, 1: string, 2: int}|string|null $prevSignificant
     */
    private function isClassReference(
        array|string|null $prevSignificant,
    ): bool {
        if ($prevSignificant === '::') {
            return true;
        }

        return is_array($prevSignificant)
            && in_array($prevSignificant[0], [T_DOUBLE_COLON, T_NEW], true);
    }

    /**
     * Read the name token (T_STRING) after a type keyword, skipping whitespace.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readTypeName(
        array $tokens,
        int $start,
    ): ?string {
        $tokenCount = count($tokens);

        for ($j = $start; $j < $tokenCount; $j++) {
            $t = $tokens[$j];
            if (!is_array($t)) {
                return null;
            }
            [$tid, $tval] = $t;
            if ($tid === T_WHITESPACE) {
                continue;
            }

            return $tid === T_STRING ? $tval : null;
        }

        return null;
    }

    /**
     * Load a class file and verify the class exists.
     *
     * Handles missing Marko package dependencies gracefully by returning false
     * (the file depends on an uninstalled optional package). Non-Marko missing
     * classes are re-thrown as real errors.
     *
     * A skip is never silent: it is recorded on this parser (skippedFiles()) and
     * in the process-wide DiscoverySkips, and outside production a warning naming
     * the skipped class and the missing class is written to the PHP error log.
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
            $missingPackage = $missingClass !== null ? MarkoException::inferPackageName($missingClass) : null;
            if (
                $missingClass !== null
                && $missingPackage !== null
            ) {
                $this->recordSkip(new DiscoverySkip($filePath, $className, $missingClass, $missingPackage));

                return false;
            }
            throw $e;
        }

        return true;
    }

    /**
     * Files this parser skipped because they reference a class from an uninstalled Marko package.
     *
     * @return list<DiscoverySkip>
     */
    public function skippedFiles(): array
    {
        return array_values($this->skippedFiles);
    }

    private function recordSkip(
        DiscoverySkip $skip,
    ): void {
        $this->skippedFiles[$skip->filePath] = $skip;

        // Several discovery passes load the same file; warn once per file per process.
        if (
            DiscoverySkips::record($skip)
            && !$this->appEnvironment->isProduction()
        ) {
            error_log('[marko] ' . $skip->message());
        }
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
            if (
                $file->isDir()
                || $file->getExtension() !== 'php'
            ) {
                continue;
            }

            yield $file;
        }
    }
}
