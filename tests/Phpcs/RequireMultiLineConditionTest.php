<?php

declare(strict_types=1);

use Marko\Tests\Support\Phpcs\PhpcsRunner;

require_once __DIR__ . '/../Support/Phpcs/PhpcsRunner.php';

const REQUIRE_MULTI_LINE_CONDITION_SNIFF = 'SlevomatCodingStandard.ControlStructures.RequireMultiLineCondition';
const REQUIRE_MULTI_LINE_CONDITION_FIXTURES = __DIR__ . '/../Fixtures/Phpcs/RequireMultiLineCondition';

describe('RequireMultiLineCondition', function (): void {
    it('reports an if condition with two checks on one line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/IfOneLine.inc',
            REQUIRE_MULTI_LINE_CONDITION_SNIFF,
        );

        expect(array_column($messages, 'line'))->toBe([9])
            ->and(array_column($messages, 'source'))
            ->toBe([REQUIRE_MULTI_LINE_CONDITION_SNIFF . '.RequiredMultiLineCondition']);
    });

    it('reports an elseif condition with two checks on one line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/ElseifOneLine.inc',
            REQUIRE_MULTI_LINE_CONDITION_SNIFF,
        );

        expect(array_column($messages, 'line'))->toBe([11])
            ->and(array_column($messages, 'source'))
            ->toBe([REQUIRE_MULTI_LINE_CONDITION_SNIFF . '.RequiredMultiLineCondition']);
    });

    it('reports a while condition with two checks on one line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/WhileOneLine.inc',
            REQUIRE_MULTI_LINE_CONDITION_SNIFF,
        );

        expect(array_column($messages, 'line'))->toBe([9])
            ->and(array_column($messages, 'source'))
            ->toBe([REQUIRE_MULTI_LINE_CONDITION_SNIFF . '.RequiredMultiLineCondition']);
    });

    it('reports a multi-line if condition with two checks sharing a line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/MultiLineSharedLine.inc',
            REQUIRE_MULTI_LINE_CONDITION_SNIFF,
        );

        expect(array_column($messages, 'line'))->toBe([10])
            ->and(array_column($messages, 'source'))
            ->toBe([REQUIRE_MULTI_LINE_CONDITION_SNIFF . '.RequiredMultiLineCondition']);
    });

    it('accepts an if condition already split with the operator leading each line', function (): void {
        $messages = new PhpcsRunner()->sniff(
            REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/IfAlreadySplit.inc',
            REQUIRE_MULTI_LINE_CONDITION_SNIFF,
        );

        expect($messages)->toBeEmpty();
    });

    it('accepts an if condition with a single check', function (): void {
        $messages = new PhpcsRunner()->sniff(
            REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/IfSingleCheck.inc',
            REQUIRE_MULTI_LINE_CONDITION_SNIFF,
        );

        expect($messages)->toBeEmpty();
    });

    it('fixes a one-line compound if condition into the parenthesized block style', function (): void {
        $fixed = new PhpcsRunner()->fix(
            REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/IfOneLine.inc',
            REQUIRE_MULTI_LINE_CONDITION_SNIFF,
        );

        expect($fixed)->toBe(file_get_contents(REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/IfOneLine.fixed.inc'));
    });

    it('splits a nested parenthesized group inside an if condition onto its own lines', function (): void {
        $fixed = new PhpcsRunner()->fix(
            REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/IfNestedGroup.inc',
            REQUIRE_MULTI_LINE_CONDITION_SNIFF,
        );

        expect($fixed)->toBe(file_get_contents(REQUIRE_MULTI_LINE_CONDITION_FIXTURES . '/IfNestedGroup.fixed.inc'));
    });
});
