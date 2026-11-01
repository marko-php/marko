<?php

declare(strict_types=1);

use Marko\Validation\Validation\RuleParser;

it('rejects an exists or unique rule string and points to RecordRules', function (string $rule, string $method): void {
    expect(fn () => new RuleParser()->parse("required|$rule"))
        ->toThrow(InvalidArgumentException::class, "RecordRules::$method(");
})->with([
    'exists with table' => ['exists:channels,id', 'exists'],
    'bare exists' => ['exists', 'exists'],
    'unique with table' => ['unique:users,email', 'unique'],
    'bare unique' => ['unique', 'unique'],
]);

it('explains that table names are not supported', function (): void {
    expect(fn () => new RuleParser()->parse('unique:users,email'))
        ->toThrow(
            InvalidArgumentException::class,
            'The unique rule has no string form, because it would name a table',
        );
});
