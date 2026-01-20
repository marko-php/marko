<?php

declare(strict_types=1);

namespace Marko\ErrorsSimple;

use SplFileObject;

/**
 * Extracts code snippets from source files for display in error reports.
 */
class CodeSnippetExtractor
{
    /**
     * Extract lines of code surrounding a specific line number.
     *
     * @param string $filePath Path to the source file
     * @param int $lineNumber The target line number (1-indexed)
     * @param int $context Number of lines to include before and after
     * @return array{lines: array<int, string>, errorLine: int}
     */
    public function extract(
        string $filePath,
        int $lineNumber,
        int $context = 5,
    ): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [
                'lines' => [],
                'errorLine' => $lineNumber,
            ];
        }

        $file = new SplFileObject($filePath);
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $startLine = max(1, $lineNumber - $context);
        $endLine = $lineNumber + $context;

        $lines = [];
        $currentLine = 1;

        foreach ($file as $line) {
            if ($currentLine >= $startLine && $currentLine <= $endLine) {
                $lines[$currentLine] = rtrim($line);
            }
            if ($currentLine > $endLine) {
                break;
            }
            $currentLine++;
        }

        return [
            'lines' => $lines,
            'errorLine' => $lineNumber,
        ];
    }
}
