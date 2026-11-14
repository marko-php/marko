<?php

declare(strict_types=1);

namespace Marko\Helpers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * The condition of a ternary: the tokens between where the expression starts and its `?`.
 *
 * This is the one place the ternary-condition boundary is computed, so every sniff
 * that needs it agrees on which tokens belong to the condition.
 */
readonly class TernaryCondition
{
    /**
     * Tokens the condition cannot reach back past. `and`, `or` and `xor` bind looser
     * than `?:`, so `$a and $b ? 1 : 2` is `$a and ($b ? 1 : 2)`. `T_COLON` is never
     * part of an expression (a ternary's `:` is `T_INLINE_ELSE`); it ends a named
     * argument's name.
     */
    private const array BOUNDARY_TOKENS = [
        T_SEMICOLON,
        T_COLON,
        T_OPEN_CURLY_BRACKET,
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_COMMA,
        T_DOUBLE_ARROW,
        T_MATCH_ARROW,
        T_FN_ARROW,
        T_RETURN,
        T_ECHO,
        T_PRINT,
        T_YIELD,
        T_YIELD_FROM,
        T_THROW,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
    ];

    public bool $isCompound;

    /**
     * @param bool $isParenthesized whether the whole condition is one parenthesized group
     * @param list<int> $topLevelOperators the boolean operators that join the condition's own checks
     */
    public function __construct(
        public int $start,
        public int $end,
        public bool $isParenthesized,
        public array $topLevelOperators,
    ) {
        $this->isCompound = $topLevelOperators !== [];
    }

    public static function fromInlineThen(
        File $phpcsFile,
        int $inlineThenPointer,
    ): self {
        $tokens = $phpcsFile->getTokens();
        $end = (int) $phpcsFile->findPrevious(Tokens::EMPTY_TOKENS, $inlineThenPointer - 1, null, true);
        $boundaryPointer = self::findBoundary($phpcsFile, $end);
        $start = (int) $phpcsFile->findNext(Tokens::EMPTY_TOKENS, $boundaryPointer + 1, null, true);
        $isParenthesized = $tokens[$start]['code'] === T_OPEN_PARENTHESIS
            && $tokens[$start]['parenthesis_closer'] === $end;

        return new self(
            start: $start,
            end: $end,
            isParenthesized: $isParenthesized,
            topLevelOperators: $isParenthesized
                ? self::findTopLevelOperators($tokens, $start + 1, $end - 1)
                : self::findTopLevelOperators($tokens, $start, $end),
        );
    }

    /**
     * The `:` that belongs to the ternary, skipping balanced groups (and any ternary
     * inside them) in its true branch.
     */
    public static function findInlineElse(
        File $phpcsFile,
        int $inlineThenPointer,
    ): int {
        $tokens = $phpcsFile->getTokens();
        $pointer = $inlineThenPointer + 1;

        while ($tokens[$pointer]['code'] !== T_INLINE_ELSE) {
            $pointer = ($tokens[$pointer]['parenthesis_closer'] ?? $tokens[$pointer]['bracket_closer'] ?? $pointer) + 1;
        }

        return $pointer;
    }

    /**
     * The boolean operators between the two pointers that are not nested in a group
     * (a call argument, an array, a closure).
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return list<int>
     */
    private static function findTopLevelOperators(
        array $tokens,
        int $start,
        int $end,
    ): array {
        $operators = [];

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            if (isset(Tokens::BOOLEAN_OPERATORS[$tokens[$pointer]['code']])) {
                $operators[] = $pointer;
            }

            $pointer = $tokens[$pointer]['parenthesis_closer']
                ?? $tokens[$pointer]['bracket_closer']
                ?? $pointer;
        }

        return $operators;
    }

    /**
     * Scans backward from the condition's last token, skipping balanced groups, to the
     * token just before the condition starts.
     */
    private static function findBoundary(
        File $phpcsFile,
        int $end,
    ): int {
        $tokens = $phpcsFile->getTokens();
        $pointer = $end;

        while (
            $pointer > 0
            && !self::isBoundary($tokens, $pointer)
        ) {
            $pointer = self::groupOpener($tokens[$pointer], $pointer) - 1;
        }

        return $pointer;
    }

    /**
     * Openers reached during the backward scan are unmatched (balanced groups are
     * skipped whole), so they end the condition. A `}` ends it too, unless it closes
     * a `match` body, which is part of the expression.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private static function isBoundary(
        array $tokens,
        int $pointer,
    ): bool {
        $code = $tokens[$pointer]['code'];

        if ($code === T_CLOSE_CURLY_BRACKET) {
            $scopeConditionPointer = $tokens[$pointer]['scope_condition'] ?? null;

            return $scopeConditionPointer === null
                || $tokens[$scopeConditionPointer]['code'] !== T_MATCH;
        }

        return isset(Tokens::ASSIGNMENT_TOKENS[$code])
            || in_array($code, self::BOUNDARY_TOKENS, true);
    }

    /**
     * The opener of the group a closing token ends, or the pointer itself for any other token.
     *
     * @param array<string, mixed> $token
     */
    private static function groupOpener(
        array $token,
        int $pointer,
    ): int {
        return match ($token['code']) {
            T_CLOSE_PARENTHESIS => $token['parenthesis_opener'],
            T_CLOSE_SQUARE_BRACKET, T_CLOSE_SHORT_ARRAY, T_CLOSE_CURLY_BRACKET => $token['bracket_opener'],
            default => $pointer,
        };
    }

    public function contains(
        int $pointer,
    ): bool {
        return $pointer >= $this->start
            && $pointer <= $this->end;
    }
}
