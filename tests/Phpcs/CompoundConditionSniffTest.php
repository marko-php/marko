<?php

declare(strict_types=1);

use Marko\Tests\Support\Phpcs\PhpcsRunner;

require_once __DIR__ . '/../Support/Phpcs/PhpcsRunner.php';

const COMPOUND_CONDITION_STANDARD = 'tools/phpcs/Marko';
const COMPOUND_CONDITION_SNIFF = 'Marko.Formatting.CompoundCondition';
const COMPOUND_CONDITION_FIXTURES = __DIR__ . '/../Fixtures/Phpcs/CompoundCondition';
const COMPOUND_CONDITION_FIX_FIXTURES = COMPOUND_CONDITION_FIXTURES . '/Fix';

describe('CompoundConditionSniff', function (): void {
    it('reports a return statement with two checks on one line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/ReturnOneLine.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([9])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine'])
            ->and(array_column($messages, 'fixable'))->toBe([true]);
    });

    it('reports an assignment with two checks on one line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/AssignmentOneLine.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([9])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
    });

    it('reports an arrow function body with two checks on one line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/ArrowFunctionOneLine.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([9])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
    });

    it('reports two checks on one line inside a function call argument', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/CallArgumentOneLine.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([9])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
    });

    it('reports two checks on one line in an array value', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/ArrayValueOneLine.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([10])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
    });

    it('reports two checks on one line in a match arm condition', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/MatchArmOneLine.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([10])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
    });

    it('reports the and and or keyword operators', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/KeywordOperators.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([9, 10])
            ->and(array_column($messages, 'source'))
            ->toBe([
                COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine',
                COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine',
            ]);
    });

    it('accepts a return statement whose continuation lines start with the operator', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/ReturnAlreadySplit.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect($messages)->toBeEmpty();
    });

    it('ignores boolean operators inside a one-line if condition', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/IfOneLine.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect($messages)->toBeEmpty();
    });

    it(
        'reports a trailing operator inside a multi-line if condition that Slevomat does not report',
        function (): void {
            $messages = new PhpcsRunner()->sniff(
                COMPOUND_CONDITION_FIXTURES . '/IfTrailingOperator.inc',
                COMPOUND_CONDITION_SNIFF,
                COMPOUND_CONDITION_STANDARD,
            );

            expect(array_column($messages, 'line'))->toBe([10])
                ->and(array_column($messages, 'source'))
                ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
        },
    );

    it('ignores boolean operators inside a compound ternary condition', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/TernaryCompoundCondition.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect($messages)->toBeEmpty();
    });

    it(
        'reports an and operator whose right side is a ternary because the ternary condition is only the right operand',
        function (): void {
            $messages = new PhpcsRunner()->sniff(
                COMPOUND_CONDITION_FIXTURES . '/LogicalAndBeforeTernary.inc',
                COMPOUND_CONDITION_SNIFF,
                COMPOUND_CONDITION_STANDARD,
            );

            expect(array_column($messages, 'line'))->toBe([9])
                ->and(array_column($messages, 'source'))
                ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
        },
    );

    it('reports a multi-line if condition whose first check shares a line with if (', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/IfHangingCondition.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([7])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.ConditionNotOnOwnLine'])
            ->and(array_column($messages, 'fixable'))->toBe([true]);
    });

    it('accepts a multi-line if condition with a single check that starts on the if line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/IfHangingSingleCheck.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect($messages)->toBeEmpty();
    });

    it('reports operators left of a compound ternary across a precedence boundary', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/TernaryBoundaries.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([12, 13, 13, 14, 15, 17, 20, 21, 22, 23, 24, 25, 26, 27])
            ->and(array_unique(array_column($messages, 'source')))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
    });

    it('reports operators nested inside a single-check ternary condition', function (): void {
        $messages = new PhpcsRunner()->sniff(
            COMPOUND_CONDITION_FIXTURES . '/TernarySingleCheckNested.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect(array_column($messages, 'line'))->toBe([9])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_CONDITION_SNIFF . '.OperatorNotOnOwnLine']);
    });
});

describe('CompoundConditionSniff fixer', function (): void {
    // Fixes `<name>.inc` with PHPCBF, expects `<name>.expected.inc`, and expects PHPCS to report that clean.
    $expectFixedToMatch = function (string $fixtureName): void {
        $runner = new PhpcsRunner();
        $expectedPath = COMPOUND_CONDITION_FIX_FIXTURES . "/$fixtureName.expected.inc";

        expect($runner->fix(
            COMPOUND_CONDITION_FIX_FIXTURES . "/$fixtureName.inc",
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        ))
            ->toBe(file_get_contents($expectedPath))
            ->and($runner->sniff($expectedPath, COMPOUND_CONDITION_SNIFF, COMPOUND_CONDITION_STANDARD))
            ->toBeEmpty();
    };

    it(
        'moves each operator in a return statement to the start of its own line',
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('ReturnStatement');
        },
    );

    it('indents continuation lines one level deeper than the statement', function () use ($expectFixedToMatch): void {
        $expectFixedToMatch('NestedStatement');
    });

    it(
        "indents an array value's continuation lines from the array element's line",
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('ArrayValue');
        },
    );

    it(
        'indents operators inside a nested group one level deeper than the group',
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('NestedGroup');
        },
    );

    it('fixes an arrow function body inside a call argument', function () use ($expectFixedToMatch): void {
        $expectFixedToMatch('ArrowFunctionArgument');
    });

    it(
        'moves a trailing operator inside a multi-line if condition to the start of the next line',
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('IfTrailingOperator');
        },
    );

    it('keeps a comment between two operands', function () use ($expectFixedToMatch): void {
        $expectFixedToMatch('CommentBetweenOperands');
    });

    it('fixes a hanging multi-line if condition into the block style', function () use ($expectFixedToMatch): void {
        $expectFixedToMatch('IfHangingCondition');
    });

    it('leaves no trailing whitespace before a moved operator', function (): void {
        $fixed = new PhpcsRunner()->fix(
            COMPOUND_CONDITION_FIX_FIXTURES . '/WhitespaceBeforeOperator.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );

        expect($fixed)
            ->toContain("/* checked first */\n        && \$name !== ''")
            ->toContain("\$named\n        && \$count > 0")
            ->and(preg_match('/[ \t]$/m', $fixed))->toBe(0);
    });

    it('produces code that phpcs reports clean', function (): void {
        $runner = new PhpcsRunner();
        $fixedPath = sys_get_temp_dir() . '/marko-compound-condition-' . bin2hex(random_bytes(8)) . '.inc';
        file_put_contents(
            $fixedPath,
            $runner->fix(
                COMPOUND_CONDITION_FIX_FIXTURES . '/Combined.inc',
                COMPOUND_CONDITION_SNIFF,
                COMPOUND_CONDITION_STANDARD,
            ),
        );

        try {
            expect($runner->sniff($fixedPath, COMPOUND_CONDITION_SNIFF, COMPOUND_CONDITION_STANDARD))->toBeEmpty();
        } finally {
            unlink($fixedPath);
        }
    });

    it('changes nothing on a second phpcbf run', function (): void {
        $runner = new PhpcsRunner();
        $fixed = $runner->fix(
            COMPOUND_CONDITION_FIX_FIXTURES . '/Combined.inc',
            COMPOUND_CONDITION_SNIFF,
            COMPOUND_CONDITION_STANDARD,
        );
        $fixedPath = sys_get_temp_dir() . '/marko-compound-condition-' . bin2hex(random_bytes(8)) . '.inc';
        file_put_contents($fixedPath, $fixed);

        try {
            expect($runner->fix($fixedPath, COMPOUND_CONDITION_SNIFF, COMPOUND_CONDITION_STANDARD))
                ->toBe($fixed);
        } finally {
            unlink($fixedPath);
        }
    });
});
