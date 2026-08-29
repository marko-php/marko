<?php

declare(strict_types=1);

namespace Marko\Tests\Support\MiddlewareDecoration;

use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Discovers middleware source files across the monorepo.
 *
 * Keys discovery off `implements MiddlewareInterface` rather than directory
 * name, so a middleware placed outside a `Middleware/` directory is not
 * silently exempt from the decoration architecture test. Walks each
 * package's `src/` directory with `RecursiveDirectoryIterator` rather than
 * `glob()`, since `glob()` has no `**` support and would silently miss a
 * middleware living directly under `src/Middleware/` (zero intermediate
 * directories).
 */
readonly class MiddlewareDiscovery
{
    /**
     * @return list<string>
     *
     * @throws UnexpectedValueException
     */
    public function discover(string $packagesRoot): array
    {
        $files = [];

        foreach ($this->packageSrcDirectories($packagesRoot) as $srcDirectory) {
            foreach ($this->phpFilesUnder($srcDirectory) as $file) {
                if ($this->implementsMiddlewareInterface($file)) {
                    $files[] = $file;
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function packageSrcDirectories(string $packagesRoot): array
    {
        $entries = scandir($packagesRoot);

        if ($entries === false) {
            return [];
        }

        $srcDirectories = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $srcDirectory = $packagesRoot . '/' . $entry . '/src';

            if (is_dir($srcDirectory)) {
                $srcDirectories[] = $srcDirectory;
            }
        }

        return $srcDirectories;
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

    /**
     * Tokenizes the file and looks for a real `implements ... MiddlewareInterface`
     * clause on a class declaration, rather than a text search — a plain
     * substring or regex match would also fire on a string literal or
     * comment that merely mentions `MiddlewareInterface`.
     */
    private function implementsMiddlewareInterface(string $file): bool
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return false;
        }

        $tokens = PhpToken::tokenize($content);
        $inImplementsClause = false;

        foreach ($tokens as $token) {
            if ($token->id === T_IMPLEMENTS) {
                $inImplementsClause = true;
                continue;
            }

            if (!$inImplementsClause) {
                continue;
            }

            if ($token->text === '{') {
                $inImplementsClause = false;
                continue;
            }

            $isNameToken = in_array(
                $token->id,
                [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE],
                true,
            );

            if ($isNameToken && str_ends_with($token->text, 'MiddlewareInterface')) {
                return true;
            }
        }

        return false;
    }
}
