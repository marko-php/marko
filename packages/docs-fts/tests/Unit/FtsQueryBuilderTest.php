<?php

declare(strict_types=1);

use Marko\DocsFts\FtsQueryBuilder;

it('lowercases and quotes each term joined with OR', function (): void {
    expect(FtsQueryBuilder::toMatchExpression('Routing Attributes'))
        ->toBe('"routing" OR "attributes"');
});

it('strips apostrophes and punctuation that break FTS5', function (): void {
    // "Marko's" must not leave a stray apostrophe in the expression.
    expect(FtsQueryBuilder::toMatchExpression("how does Marko's module system work?"))
        ->toBe('"marko" OR "module" OR "system" OR "work"');
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

it('joins terms with OR rather than implicit AND', function (): void {
    expect(FtsQueryBuilder::toMatchExpression('observers events'))
        ->toBe('"observers" OR "events"');
});

it('quotes an FTS5 keyword term so it is not treated as an operator', function (): void {
    // "near" is a valid search term but also an FTS5 operator — quoting neutralizes it.
    expect(FtsQueryBuilder::toMatchExpression('near cache'))
        ->toBe('"near" OR "cache"');
});

it('falls back to all tokens when every token is a stop word', function (): void {
    expect(FtsQueryBuilder::toMatchExpression('how do you'))
        ->toBe('"how" OR "do" OR "you"');
});

it('returns an empty expression for input with no word characters', function (): void {
    expect(FtsQueryBuilder::toMatchExpression('!!! "" ((('))->toBe('');
});
