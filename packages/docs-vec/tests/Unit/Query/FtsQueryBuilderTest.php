<?php

declare(strict_types=1);

use Marko\DocsVec\Query\FtsQueryBuilder;

it('lowercases and quotes each term joined with OR', function (): void {
    expect(FtsQueryBuilder::toMatchExpression('Routing Attributes'))
        ->toBe('"routing" OR "attributes"');
});

it('strips apostrophes and punctuation that break FTS5', function (): void {
    expect(FtsQueryBuilder::toMatchExpression("how does Marko's module system work?"))
        ->toBe('"marko" OR "module" OR "system" OR "work"');
});

it('neutralizes a raw FTS5 operator expression to a safe term', function (): void {
    // "NEAR/" raw is a syntax error; sanitized it becomes a quoted term.
    expect(FtsQueryBuilder::toMatchExpression('NEAR/'))
        ->toBe('"near"');
});

it('drops common stop words to improve precision', function (): void {
    $expr = FtsQueryBuilder::toMatchExpression('how do observers react to events');

    expect($expr)->toContain('"observers"')
        ->and($expr)->toContain('"react"')
        ->and($expr)->toContain('"events"')
        ->and($expr)->not->toContain('"how"')
        ->and($expr)->not->toContain('"do"')
        ->and($expr)->not->toContain('"to"');
});

it('returns an empty expression when no usable term remains', function (): void {
    expect(FtsQueryBuilder::toMatchExpression('!!! ??? ...'))->toBe('');
});
