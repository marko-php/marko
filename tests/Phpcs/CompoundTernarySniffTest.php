<?php

declare(strict_types=1);

use Marko\Tests\Support\Phpcs\PhpcsRunner;

require_once __DIR__ . '/../Support/Phpcs/PhpcsRunner.php';

const COMPOUND_TERNARY_STANDARD = 'tools/phpcs/Marko';
const COMPOUND_TERNARY_SNIFF = 'Marko.Formatting.CompoundTernary';
const COMPOUND_TERNARY_FIXTURES = __DIR__ . '/../Fixtures/Phpcs/CompoundTernary';
const COMPOUND_TERNARY_FIX_FIXTURES = COMPOUND_TERNARY_FIXTURES . '/Fix';

describe('CompoundTernarySniff', function (): void {
    // Runs only this sniff, so the boolean-operator sniff's messages stay out of the assertions.
    $sniff = fn (string $fixtureName): array => new PhpcsRunner()->sniff(
        COMPOUND_TERNARY_FIXTURES . "/$fixtureName.inc",
        COMPOUND_TERNARY_SNIFF,
        COMPOUND_TERNARY_STANDARD,
    );

    it('reports a compound ternary condition without parentheses', function () use ($sniff): void {
        $messages = $sniff('NotParenthesized');

        expect(array_column($messages, 'line'))->toBe([8])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_TERNARY_SNIFF . '.ConditionNotParenthesized'])
            ->and(array_column($messages, 'fixable'))->toBe([true]);
    });

    it('reports a compound ternary with the question mark on the condition line', function () use ($sniff): void {
        $messages = $sniff('QuestionMarkOnConditionLine');

        expect(array_column($messages, 'line'))->toBe([8])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_TERNARY_SNIFF . '.QuestionMarkNotOnOwnLine'])
            ->and(array_column($messages, 'fixable'))->toBe([true]);
    });

    it('reports a compound ternary condition split across lines', function () use ($sniff): void {
        $messages = $sniff('ConditionSplit');

        expect(array_column($messages, 'line'))->toBe([8])
            ->and(array_column($messages, 'source'))
            ->toBe([COMPOUND_TERNARY_SNIFF . '.ConditionNotOnOneLine'])
            ->and(array_column($messages, 'fixable'))->toBe([true]);
    });

    it(
        'accepts a parenthesized one-line condition with question mark and colon on their own lines',
        function () use ($sniff): void {
            expect($sniff('Formatted'))->toBeEmpty();
        },
    );

    it('ignores a ternary with a single-check condition', function () use ($sniff): void {
        expect($sniff('SingleCheck'))->toBeEmpty();
    });

    it(
        'ignores a ternary whose left side is joined by and because the condition is only the right operand',
        function () use ($sniff): void {
            expect($sniff('LogicalAndBeforeTernary'))->toBeEmpty();
        },
    );

    it(
        'ignores a ternary whose boolean operator sits before an assignment in the condition',
        function () use ($sniff): void {
            expect($sniff('OperatorBeforeAssignment'))->toBeEmpty();
        },
    );

    it('ignores a compound ternary inside an if condition', function () use ($sniff): void {
        expect($sniff('InsideControlStructure'))->toBeEmpty();
    });

    it(
        'accepts a compound ternary condition whose nested call arguments span several lines',
        function () use ($sniff): void {
            expect($sniff('NestedCallSpansLines'))->toBeEmpty();
        },
    );
});

describe('CompoundTernarySniff fixer', function (): void {
    // Fixes `<name>.inc` with PHPCBF and the whole Marko standard, expects `<name>.expected.inc`,
    // expects PHPCS to report that clean, and expects a second PHPCBF run to change nothing.
    $expectFixedToMatch = function (string $fixtureName): void {
        $runner = new PhpcsRunner();
        $expectedPath = COMPOUND_TERNARY_FIX_FIXTURES . "/$fixtureName.expected.inc";

        expect($runner->fix(COMPOUND_TERNARY_FIX_FIXTURES . "/$fixtureName.inc", standard: COMPOUND_TERNARY_STANDARD))
            ->toBe(file_get_contents($expectedPath))
            ->and($runner->sniff($expectedPath, standard: COMPOUND_TERNARY_STANDARD))->toBeEmpty()
            ->and($runner->fix($expectedPath, standard: COMPOUND_TERNARY_STANDARD))
            ->toBe(file_get_contents($expectedPath));
    };

    it(
        'fixes a one-line compound ternary into the parenthesized multi-line form',
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('OneLine');
        },
    );

    it(
        'fixes a ternary inside a named argument without changing precedence',
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('NamedArgument');
        },
    );

    it(
        'joins a compound ternary condition split across lines into one line',
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('Split');
        },
    );

    it(
        'joins a compound ternary condition with a newline inside its wrapping parentheses',
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('SplitInsideParentheses');
        },
    );

    it(
        'moves the colon that belongs to the compound ternary when its true branch holds another ternary',
        function () use ($expectFixedToMatch): void {
            $expectFixedToMatch('NestedTernaryInTrueBranch');
        },
    );

    it('treats a short ternary with a compound left side the same way', function () use ($expectFixedToMatch): void {
        $expectFixedToMatch('ShortTernary');
    });

    it('wraps only the condition after echo', function () use ($expectFixedToMatch): void {
        $expectFixedToMatch('Echo');
    });
});
