<?php

declare(strict_types=1);

namespace Marko\Sniffs\Formatting;

use Marko\Helpers\LineLayout;
use Marko\Helpers\TernaryCondition;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Reports a boolean operator that does not start its line.
 */
class CompoundConditionSniff implements Sniff
{
    public const string CODE_OPERATOR_NOT_ON_OWN_LINE = 'OperatorNotOnOwnLine';

    public const string CODE_CONDITION_NOT_ON_OWN_LINE = 'ConditionNotOnOwnLine';

    private const array CONTROL_STRUCTURES = [
        T_IF,
        T_ELSEIF,
        T_WHILE,
    ];

    private const int INDENT_SIZE = 4;

    /**
     * Tokens the expression an operator belongs to cannot reach back past.
     */
    private const array EXPRESSION_BOUNDARIES = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
        T_DOUBLE_ARROW,
        T_FN_ARROW,
    ];

    /**
     * @return list<int|string>
     */
    public function register(): array
    {
        return [
            ...array_values(Tokens::BOOLEAN_OPERATORS),
            ...self::CONTROL_STRUCTURES,
        ];
    }

    public function process(
        File $phpcsFile,
        int $stackPtr,
    ): void {
        if (in_array($phpcsFile->getTokens()[$stackPtr]['code'], self::CONTROL_STRUCTURES, true)) {
            $this->processControlStructure($phpcsFile, $stackPtr);

            return;
        }

        if (
            LineLayout::startsItsLine($phpcsFile, $stackPtr)
            || $this->isInsideSlevomatOwnedCondition($phpcsFile, $stackPtr)
            || $this->isInsideCompoundTernaryCondition($phpcsFile, $stackPtr)
        ) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Put each check of a compound condition on its own line, with the boolean operator leading the line',
            $stackPtr,
            self::CODE_OPERATOR_NOT_ON_OWN_LINE,
        );

        if ($fix) {
            $this->moveOperatorToOwnLine($phpcsFile, $stackPtr);
        }
    }

    /**
     * Starts a new line at the operator, one level deeper than the line where the expression starts.
     */
    private function moveOperatorToOwnLine(
        File $phpcsFile,
        int $operatorPointer,
    ): void {
        $tokens = $phpcsFile->getTokens();
        $nextPointer = (int) $phpcsFile->findNext(Tokens::EMPTY_TOKENS, $operatorPointer + 1, null, true);

        if ($tokens[$nextPointer]['line'] !== $tokens[$operatorPointer]['line']) {
            $this->moveTrailingOperator($phpcsFile, $operatorPointer, $nextPointer);

            return;
        }

        $groupOpenerPointer = $this->findGroupOpener($phpcsFile, $operatorPointer);

        if ($groupOpenerPointer !== null) {
            $this->moveOperatorInsideGroup($phpcsFile, $operatorPointer, $groupOpenerPointer);

            return;
        }

        $expressionStartPointer = $this->findExpressionStart($phpcsFile, $operatorPointer);
        $indentation = LineLayout::indentation($phpcsFile, $expressionStartPointer) + self::INDENT_SIZE;

        $phpcsFile->fixer->beginChangeset();
        LineLayout::startNewLineBefore($phpcsFile, $operatorPointer, $indentation);
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Moves an operator that ends its line to the start of the next check, keeping that
     * line's indentation.
     */
    private function moveTrailingOperator(
        File $phpcsFile,
        int $operatorPointer,
        int $nextCheckPointer,
    ): void {
        $tokens = $phpcsFile->getTokens();

        $phpcsFile->fixer->beginChangeset();

        if ($tokens[$operatorPointer - 1]['code'] === T_WHITESPACE) {
            $phpcsFile->fixer->replaceToken($operatorPointer - 1, '');
        }

        $phpcsFile->fixer->replaceToken($operatorPointer, '');
        $phpcsFile->fixer->addContentBefore($nextCheckPointer, $tokens[$operatorPointer]['content'] . ' ');
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Splits a parenthesized group the way Slevomat splits a control-structure condition:
     * a new line after `(`, the checks one level deeper, and `)` on its own line.
     *
     * Waits while the operator before the group has still to move, because the group's
     * indentation follows the line `(` ends up on.
     */
    private function moveOperatorInsideGroup(
        File $phpcsFile,
        int $operatorPointer,
        int $groupOpenerPointer,
    ): void {
        $tokens = $phpcsFile->getTokens();
        $operatorBeforeGroupPointer = (int) $phpcsFile->findPrevious(
            Tokens::EMPTY_TOKENS,
            $groupOpenerPointer - 1,
            null,
            true,
        );

        if (!LineLayout::startsItsLine($phpcsFile, $operatorBeforeGroupPointer)) {
            return;
        }

        $groupIndentation = LineLayout::indentation($phpcsFile, $groupOpenerPointer);
        $indentation = $groupIndentation + self::INDENT_SIZE;

        $phpcsFile->fixer->beginChangeset();
        LineLayout::startNewLineBefore($phpcsFile, $groupOpenerPointer + 1, $indentation);
        LineLayout::startNewLineBefore($phpcsFile, $operatorPointer, $indentation);
        LineLayout::startNewLineBefore(
            $phpcsFile,
            $tokens[$groupOpenerPointer]['parenthesis_closer'],
            $groupIndentation,
        );
        $phpcsFile->fixer->endChangeset();
    }

    /**
     * The parenthesis directly around the operator when it groups an operand of another
     * boolean operator, as in `$a && ($b || $c)`.
     */
    private function findGroupOpener(
        File $phpcsFile,
        int $operatorPointer,
    ): ?int {
        $tokens = $phpcsFile->getTokens();
        $openerPointer = array_key_last($tokens[$operatorPointer]['nested_parenthesis'] ?? []);

        if (
            $openerPointer === null
            || isset($tokens[$openerPointer]['parenthesis_owner'])
        ) {
            return null;
        }

        $previousPointer = $phpcsFile->findPrevious(Tokens::EMPTY_TOKENS, $openerPointer - 1, null, true);

        return isset(Tokens::BOOLEAN_OPERATORS[$tokens[$previousPointer]['code']])
            ? $openerPointer
            : null;
    }

    /**
     * The first token of the expression the operator belongs to.
     */
    private function findExpressionStart(
        File $phpcsFile,
        int $operatorPointer,
    ): int {
        $tokens = $phpcsFile->getTokens();
        $pointer = $operatorPointer - 1;

        while (
            $pointer > 0
            && !in_array($tokens[$pointer]['code'], self::EXPRESSION_BOUNDARIES, true)
        ) {
            $pointer--;
        }

        return (int) $phpcsFile->findNext(Tokens::EMPTY_TOKENS, $pointer + 1, null, true);
    }

    /**
     * Reports the hanging form of a multi-line condition Slevomat leaves alone: the
     * first check sharing its line with the opening parenthesis.
     */
    private function processControlStructure(
        File $phpcsFile,
        int $controlStructurePointer,
    ): void {
        $tokens = $phpcsFile->getTokens();
        $openerPointer = $tokens[$controlStructurePointer]['parenthesis_opener'];

        if (
            $this->countOperators($phpcsFile, $openerPointer) === 0
            || $this->slevomatReportsCondition($phpcsFile, $openerPointer)
        ) {
            return;
        }

        $conditionStartPointer = $phpcsFile->findNext(Tokens::EMPTY_TOKENS, $openerPointer + 1, null, true);

        if ($tokens[$conditionStartPointer]['line'] !== $tokens[$openerPointer]['line']) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Start a multi-line condition on the line after the opening parenthesis, with each check on its own line',
            $controlStructurePointer,
            self::CODE_CONDITION_NOT_ON_OWN_LINE,
        );

        if ($fix) {
            $indentation = LineLayout::indentation($phpcsFile, $controlStructurePointer);
            $closerPointer = $tokens[$openerPointer]['parenthesis_closer'];

            $phpcsFile->fixer->beginChangeset();
            LineLayout::startNewLineBefore($phpcsFile, $conditionStartPointer, $indentation + self::INDENT_SIZE);
            LineLayout::startNewLineBefore($phpcsFile, $closerPointer, $indentation);
            $phpcsFile->fixer->endChangeset();
        }
    }

    /**
     * Whether the pointer sits inside an if/elseif/while condition that
     * SlevomatCodingStandard.ControlStructures.RequireMultiLineCondition reports.
     */
    private function isInsideSlevomatOwnedCondition(
        File $phpcsFile,
        int $pointer,
    ): bool {
        $tokens = $phpcsFile->getTokens();

        return array_any(
            array_keys($tokens[$pointer]['nested_parenthesis'] ?? []),
            fn (int $openerPointer): bool => $this->isControlStructureCondition($phpcsFile, $openerPointer)
                && $this->slevomatReportsCondition($phpcsFile, $openerPointer),
        );
    }

    /**
     * Whether the pointer sits inside a compound ternary condition, which the ternary sniff owns.
     */
    private function isInsideCompoundTernaryCondition(
        File $phpcsFile,
        int $pointer,
    ): bool {
        $inlineThenPointer = $phpcsFile->findNext(T_INLINE_THEN, $pointer + 1);

        while ($inlineThenPointer !== false) {
            $condition = TernaryCondition::fromInlineThen($phpcsFile, $inlineThenPointer);

            if (
                $condition->isCompound
                && $condition->contains($pointer)
            ) {
                return true;
            }

            $inlineThenPointer = $phpcsFile->findNext(T_INLINE_THEN, $inlineThenPointer + 1);
        }

        return false;
    }

    private function isControlStructureCondition(
        File $phpcsFile,
        int $openerPointer,
    ): bool {
        $tokens = $phpcsFile->getTokens();
        $ownerPointer = $tokens[$openerPointer]['parenthesis_owner'] ?? null;

        return $ownerPointer !== null
            && in_array($tokens[$ownerPointer]['code'], self::CONTROL_STRUCTURES, true);
    }

    /**
     * Mirrors RequireMultiLineConditionSniff::process() with minLineLength 0 and
     * alwaysSplitAllConditionParts on: a one-line condition is always reported, a
     * multi-line one when it has fewer lines than parts.
     */
    private function slevomatReportsCondition(
        File $phpcsFile,
        int $openerPointer,
    ): bool {
        $tokens = $phpcsFile->getTokens();
        $closerPointer = $tokens[$openerPointer]['parenthesis_closer'];
        $conditionStartPointer = $phpcsFile->findNext(Tokens::EMPTY_TOKENS, $openerPointer + 1, $closerPointer, true);
        $conditionEndPointer = $phpcsFile->findPrevious(
            Tokens::EMPTY_TOKENS,
            $closerPointer - 1,
            $openerPointer,
            true,
        );
        $lineCount = $tokens[$conditionEndPointer]['line'] - $tokens[$conditionStartPointer]['line'] + 1;

        return $lineCount === 1
            || $lineCount < $this->countOperators($phpcsFile, $openerPointer) + 1;
    }

    /**
     * Counts every boolean operator between the parentheses, nested ones included.
     */
    private function countOperators(
        File $phpcsFile,
        int $openerPointer,
    ): int {
        $tokens = $phpcsFile->getTokens();
        $closerPointer = $tokens[$openerPointer]['parenthesis_closer'];

        return count(array_filter(
            array_slice($tokens, $openerPointer + 1, $closerPointer - $openerPointer - 1),
            fn (array $token): bool => isset(Tokens::BOOLEAN_OPERATORS[$token['code']]),
        ));
    }
}
