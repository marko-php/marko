<?php

declare(strict_types=1);

use Marko\Validation\Contracts\WildcardAwareRuleInterface;
use Marko\Validation\Rules\After;
use Marko\Validation\Rules\AfterOrEqual;
use Marko\Validation\Rules\Before;
use Marko\Validation\Rules\BeforeOrEqual;
use Marko\Validation\Validation\Validator;

describe('against a date', function (): void {
    it('applies each rule\'s operator', function (string $rule, string $value, bool $expected): void {
        $instance = new $rule('2026-06-15');

        expect($instance->passes('at', $value, []))->toBe($expected);
    })->with([
        'after: later' => [After::class, '2026-06-16', true],
        'after: equal' => [After::class, '2026-06-15', false],
        'after: earlier' => [After::class, '2026-06-14', false],
        'after_or_equal: equal' => [AfterOrEqual::class, '2026-06-15', true],
        'after_or_equal: earlier' => [AfterOrEqual::class, '2026-06-14', false],
        'before: earlier' => [Before::class, '2026-06-14', true],
        'before: equal' => [Before::class, '2026-06-15', false],
        'before_or_equal: equal' => [BeforeOrEqual::class, '2026-06-15', true],
        'before_or_equal: later' => [BeforeOrEqual::class, '2026-06-16', false],
    ]);

    it('names the reference in its message', function (): void {
        expect(new After('today')->message('at', 'x'))->toBe('The at field must be a date after today.')
            ->and(new AfterOrEqual('today')->message('at', 'x'))->toBe(
                'The at field must be a date after or equal to today.',
            )
            ->and(new Before('today')->message('at', 'x'))->toBe('The at field must be a date before today.')
            ->and(new BeforeOrEqual('today')->message('at', 'x'))->toBe(
                'The at field must be a date before or equal to today.',
            );
    });

    it('accepts relative dates and DateTimeInterface values', function (): void {
        expect(new After('yesterday')->passes('at', 'tomorrow', []))->toBeTrue()
            ->and(new Before('tomorrow')->passes('at', new DateTimeImmutable('today'), []))->toBeTrue();
    });

    it('fails a value that is not a date', function (mixed $value): void {
        expect(new After('2026-01-01')->passes('at', $value, []))->toBeFalse();
    })->with(['garbage' => ['not a date'], 'integer' => [20260101], 'array' => [['2026-02-01']]]);

    it('passes an empty value', function (): void {
        expect(new After('2026-01-01')->passes('at', null, []))->toBeTrue()
            ->and(new After('2026-01-01')->passes('at', '', []))->toBeTrue();
    });
});

describe('against another field', function (): void {
    it('compares with the field\'s value', function (): void {
        $data = ['starts_at' => '2026-06-15 09:00', 'ends_at' => '2026-06-15 10:00'];

        expect(new After('starts_at')->passes('ends_at', '2026-06-15 10:00', $data))->toBeTrue()
            ->and(new Before('starts_at')->passes('ends_at', '2026-06-15 10:00', $data))->toBeFalse()
            ->and(new After('starts_at')->message('ends_at', 'x'))->toBe(
                'The ends_at field must be a date after starts_at.',
            );
    });

    it('prefers a field present in the data over a date of the same name', function (): void {
        $data = ['today' => '2000-01-01'];

        expect(new After('today')->passes('at', '2001-01-01', $data))->toBeTrue();
    });

    it('fails when the referenced field is present but is not a date', function (mixed $other): void {
        expect(new After('starts_at')->passes('ends_at', '2026-06-15', ['starts_at' => $other]))->toBeFalse();
    })->with(['garbage' => ['soon'], 'null' => [null], 'array' => [[]]]);

    it('implements WildcardAwareRuleInterface and fills the row index', function (): void {
        $rule = new After('items.*.starts_at');
        $data = ['items' => [['starts_at' => '2026-01-01'], ['starts_at' => '2026-03-01']]];
        $row1 = $rule->forWildcardIndexes('items.*.ends_at', ['1']);

        expect($rule)->toBeInstanceOf(WildcardAwareRuleInterface::class)
            ->and($row1->passes('items.1.ends_at', '2026-02-01', $data))->toBeFalse()
            ->and($row1->message('items.1.ends_at', 'x'))->toBe(
                'The items.1.ends_at field must be a date after items.1.starts_at.',
            )
            ->and($rule->forWildcardIndexes('items.*.ends_at', ['0'])->passes('items.0.ends_at', '2026-02-01', $data))
            ->toBeTrue();
    });

    it('throws when the field has more wildcards than the key', function (): void {
        new Before('items.*.starts_at')->forWildcardIndexes('ends_at', []);
    })->throws(
        InvalidArgumentException::class,
        'The before:items.*.starts_at rule has more wildcards than the ends_at key it validates',
    );
});

it('throws when the parameter is neither a date nor a field present in the data', function (): void {
    new After('starts_att')->passes('ends_at', '2026-06-15', ['starts_at' => '2026-06-14']);
})->throws(
    InvalidArgumentException::class,
    'The after:starts_att rule names neither a date nor a field present in the data',
);

it('throws for an empty parameter', function (): void {
    new AfterOrEqual('');
})->throws(
    InvalidArgumentException::class,
    'The after_or_equal rule needs a date or a field, e.g. after_or_equal:today or after_or_equal:starts_at',
);

it('validates after:items.*.starts_at under items.*.ends_at', function (): void {
    $errors = new Validator()->validate(
        ['items' => [
            ['starts_at' => '2026-01-01', 'ends_at' => '2026-01-02'],
            ['starts_at' => '2026-03-01', 'ends_at' => '2026-02-01'],
        ]],
        ['items.*.ends_at' => 'required|date|after:items.*.starts_at'],
    );

    expect($errors->all())->toBe([
        'items.1.ends_at' => ['The items.1.ends_at field must be a date after items.1.starts_at.'],
    ]);
});

it('throws up front for a reference with more wildcards than the key, even with no rows', function (): void {
    new Validator()->validate([], ['ends_at' => 'after:items.*.starts_at']);
})->throws(InvalidArgumentException::class, 'has more wildcards than the ends_at key');
