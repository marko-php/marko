<?php

declare(strict_types=1);

namespace Marko\Sniffs\Formatting;

use Marko\Helpers\LineLayout;
use Marko\Helpers\TernaryCondition;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Formats a ternary whose condition has more than one check: the condition wrapped in
 * parentheses on one line, and `?` and `:` each starting their own line.
 */
class CompoundTernarySniff implements Sniff
{
    public const string CODE_CONDITION_NOT_PARENTHESIZED = 'ConditionNotParenthesized';

    public const string CODE_QUESTION_MARK_NOT_ON_OWN_LINE = 'QuestionMarkNotOnOwnLine';

    public const string CODE_CONDITION_NOT_ON_ONE_LINE = 'ConditionNotOnOneLine';

    public const string CODE_COLON_NOT_ON_OWN_LINE = 'ColonNotOnOwnLine';

    private const int INDENT_SIZE = 4;

    private const array CONTROL_STRUCTURES = [
        T_IF,
        T_ELSEIF,
        T_WHILE,
    ];

    /**
     * @return list<int|string>
     */
    public function register(): array
    {
        return [T_INLINE_THEN];
    }

    public function process(
        File $phpcsFile,
        int $stackPtr,
    ): void {
        $condition = TernaryCondition::fromInlineThen($phpcsFile, $stackPtr);

        if (
            !$condition->isCompound
            || $this->isInsideControlStructureCondition($phpcsFile, $stackPtr)
        ) {
            return;
        }

        if (
            !$condition->isParenthesized
            && $phpcsFile->addFixableError(
                'Wrap a compound ternary condition in parentheses',
                $condition->start,
                self::CODE_CONDITION_NOT_PARENTHESIZED,
            )
        ) {
            $phpcsFile->fixer->beginChangeset();
            $phpcsFile->fixer->addContentBefore($condition->start, '(');
            $phpcsFile->fixer->addContent($condition->end, ')');
            $phpcsFile->fixer->endChangeset();
        }

        if (
            $this->isSplit($phpcsFile, $condition)
            && $phpcsFile->addFixableError(
                'Keep a compound ternary condition on one line',
                $condition->start,
                self::CODE_CONDITION_NOT_ON_ONE_LINE,
            )
        ) {
            $this->joinCondition($phpcsFile, $condition);
        }

        $indentation = LineLayout::indentation($phpcsFile, $condition->start) + self::INDENT_SIZE;

        if (
            !LineLayout::startsItsLine($phpcsFile, $stackPtr)
            && $phpcsFile->addFixableError(
                'Put the ? of a compound ternary at the start of its own line',
                $stackPtr,
                self::CODE_QUESTION_MARK_NOT_ON_OWN_LINE,
            )
        ) {
            LineLayout::startNewLineBefore($phpcsFile, $stackPtr, $indentation);
        }

        $inlineElsePointer = TernaryCondition::findInlineElse($phpcsFile, $stackPtr);
        $isShortTernary = $inlineElsePointer === $stackPtr + 1;

        if (
            !$isShortTernary
            && !LineLayout::startsItsLine($phpcsFile, $inlineElsePointer)
            && $phpcsFile->addFixableError(
                'Put the : of a compound ternary at the start of its own line',
                $inlineElsePointer,
                self::CODE_COLON_NOT_ON_OWN_LINE,
            )
        ) {
            LineLayout::startNewLineBefore($phpcsFile, $inlineElsePointer, $indentation);
        }
    }

    /**
     * Joins the condition's checks onto one line by collapsing the whitespace before each
     * top-level operator into one space. Line breaks nested inside calls, arrays or
     * closures are left alone, because other sniffs may require them.
     */
    private function joinCondition(
        File $phpcsFile,
        TernaryCondition $condition,
    ): void {
        $phpcsFile->fixer->beginChangeset();

        foreach ($condition->topLevelOperators as $operatorPointer) {
            $this->collapseWhitespaceBefore($phpcsFile, $operatorPointer);
        }

        if ($condition->isParenthesized) {
            $this->removeWhitespaceBetween($phpcsFile, $condition->start, $this->firstInside($phpcsFile, $condition));
            $this->removeWhitespaceBetween($phpcsFile, $this->lastInside($phpcsFile, $condition), $condition->end);
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Whether the condition's checks are spread over several lines: a top-level operator
     * starts a line, or a line break follows the wrapping `(` or precedes its `)`.
     */
    private function isSplit(
        File $phpcsFile,
        TernaryCondition $condition,
    ): bool {
        if (array_any(
            $condition->topLevelOperators,
            fn (int $operatorPointer): bool => LineLayout::startsItsLine($phpcsFile, $operatorPointer),
        )) {
            return true;
        }

        if (!$condition->isParenthesized) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        return $tokens[$this->firstInside($phpcsFile, $condition)]['line'] !== $tokens[$condition->start]['line']
            || $tokens[$this->lastInside($phpcsFile, $condition)]['line'] !== $tokens[$condition->end]['line'];
    }

    /**
     * The first token inside the condition's wrapping parentheses.
     */
    private function firstInside(
        File $phpcsFile,
        TernaryCondition $condition,
    ): int {
        return (int) $phpcsFile->findNext(T_WHITESPACE, $condition->start + 1, null, true);
    }

    /**
     * The last token inside the condition's wrapping parentheses.
     */
    private function lastInside(
        File $phpcsFile,
        TernaryCondition $condition,
    ): int {
        return (int) $phpcsFile->findPrevious(T_WHITESPACE, $condition->end - 1, null, true);
    }

    private function removeWhitespaceBetween(
        File $phpcsFile,
        int $fromPointer,
        int $toPointer,
    ): void {
        for ($pointer = $fromPointer + 1; $pointer < $toPointer; $pointer++) {
            $phpcsFile->fixer->replaceToken($pointer, '');
        }
    }

    /**
     * Replaces the run of whitespace tokens directly before the pointer with one space.
     */
    private function collapseWhitespaceBefore(
        File $phpcsFile,
        int $pointer,
    ): void {
        $firstWhitespacePointer = (int) $phpcsFile->findPrevious(T_WHITESPACE, $pointer - 1, null, true) + 1;

        for ($whitespacePointer = $firstWhitespacePointer; $whitespacePointer < $pointer; $whitespacePointer++) {
            $phpcsFile->fixer->replaceToken(
                $whitespacePointer,
                $whitespacePointer === $firstWhitespacePointer ? ' ' : '',
            );
        }
    }

    /**
     * Whether the pointer sits between the parentheses of an if/elseif/while condition.
     * SlevomatCodingStandard.ControlStructures.RequireMultiLineCondition splits every
     * boolean operator there, nested ones included, so joining them here would make
     * PHPCBF oscillate.
     */
    private function isInsideControlStructureCondition(
        File $phpcsFile,
        int $pointer,
    ): bool {
        $tokens = $phpcsFile->getTokens();

        return array_any(
            array_keys($tokens[$pointer]['nested_parenthesis'] ?? []),
            fn (int $openerPointer): bool => in_array(
                $tokens[$tokens[$openerPointer]['parenthesis_owner'] ?? $openerPointer]['code'],
                self::CONTROL_STRUCTURES,
                true,
            ),
        );
    }
}
