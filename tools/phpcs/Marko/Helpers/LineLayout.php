<?php

declare(strict_types=1);

namespace Marko\Helpers;

use PHP_CodeSniffer\Files\File;

/**
 * Line and indentation helpers shared by the Marko formatting sniffs.
 */
class LineLayout
{
    /**
     * Whether the pointer is the first non-whitespace token on its line.
     */
    public static function startsItsLine(
        File $phpcsFile,
        int $pointer,
    ): bool {
        $tokens = $phpcsFile->getTokens();
        $previousPointer = $phpcsFile->findPrevious(T_WHITESPACE, $pointer - 1, null, true);

        return $previousPointer === false
            || $tokens[$previousPointer]['line'] !== $tokens[$pointer]['line'];
    }

    /**
     * The number of spaces that indent the line the pointer is on.
     */
    public static function indentation(
        File $phpcsFile,
        int $pointer,
    ): int {
        $tokens = $phpcsFile->getTokens();
        $firstPointer = $pointer;

        while (
            $firstPointer > 0
            && $tokens[$firstPointer - 1]['line'] === $tokens[$pointer]['line']
        ) {
            $firstPointer--;
        }

        return $tokens[$firstPointer]['code'] === T_WHITESPACE
            ? strlen($tokens[$firstPointer]['content'])
            : 0;
    }

    /**
     * Puts the pointer at the start of a new line indented by the given number of spaces,
     * replacing the whitespace before it.
     */
    public static function startNewLineBefore(
        File $phpcsFile,
        int $pointer,
        int $indentation,
    ): void {
        $tokens = $phpcsFile->getTokens();
        $newLine = $phpcsFile->eolChar . str_repeat(' ', $indentation);

        if ($tokens[$pointer - 1]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer->replaceToken($pointer - 1, $newLine);
        } else {
            $phpcsFile->fixer->addContentBefore($pointer, $newLine);
        }
    }
}
