<?php

declare(strict_types=1);

use Marko\Validation\Contracts\WildcardAwareRuleInterface;
use Marko\Validation\Rules\Distinct;
use Marko\Validation\Validation\Validator;

it('implements WildcardAwareRuleInterface', function (): void {
    expect(new Distinct())->toBeInstanceOf(WildcardAwareRuleInterface::class);
});

it('flags every duplicate across items.*.sku', function (): void {
    $errors = new Validator()->validate(
        ['items' => [['sku' => 'A1'], ['sku' => 'B2'], ['sku' => 'A1'], ['name' => 'no sku']]],
        ['items.*.sku' => 'distinct'],
    );

    expect($errors->all())->toBe([
        'items.0.sku' => ['The items.0.sku field has a duplicate value.'],
        'items.2.sku' => ['The items.2.sku field has a duplicate value.'],
    ]);
});

it('passes when every value is distinct', function (): void {
    expect(new Validator()->passes(
        ['items' => [['sku' => 'A1'], ['sku' => 'a1'], ['sku' => 'B2']]],
        ['items.*.sku' => 'distinct'],
    ))->toBeTrue();
});

it('ignores case with ignore_case', function (): void {
    $errors = new Validator()->validate(
        ['items' => [['sku' => 'Äb1'], ['sku' => 'äB1']]],
        ['items.*.sku' => 'distinct:ignore_case'],
    );

    expect(array_keys($errors->all()))->toBe(['items.0.sku', 'items.1.sku']);
});

it('compares numeric values by value unless strict', function (): void {
    $validator = new Validator();
    $data = ['ids' => [1, '1', '01']];

    expect(array_keys($validator->validate($data, ['ids.*' => 'distinct'])->all()))->toBe(['ids.0', 'ids.1', 'ids.2'])
        ->and($validator->passes($data, ['ids.*' => 'distinct:strict']))->toBeTrue()
        ->and($validator->passes(['ids' => [1, 1]], ['ids.*' => 'distinct:strict']))->toBeFalse();
});

it('combines strict and ignore_case', function (): void {
    $validator = new Validator();

    expect($validator->passes(['tags' => ['PHP', 'php']], ['tags.*' => 'distinct:strict,ignore_case']))->toBeFalse()
        ->and($validator->passes(['tags' => [1, '1']], ['tags.*' => 'distinct:strict,ignore_case']))->toBeTrue();
});

it('compares across every row of a nested wildcard key', function (): void {
    $errors = new Validator()->validate(
        ['orders' => [['lines' => [['sku' => 'A'], ['sku' => 'B']]], ['lines' => [['sku' => 'A']]]]],
        ['orders.*.lines.*.sku' => 'distinct'],
    );

    expect(array_keys($errors->all()))->toBe(['orders.0.lines.0.sku', 'orders.1.lines.0.sku']);
});

it('throws for a key without a wildcard', function (): void {
    new Validator()->validate(['sku' => 'A'], ['sku' => 'distinct']);
})->throws(
    InvalidArgumentException::class,
    'The distinct rule compares the items of an array, so its key needs a wildcard such as items.*.sku; sku has none',
);

it('throws when used before being bound to a wildcard key', function (): void {
    new Distinct()->passes('items.0.sku', 'A', []);
})->throws(InvalidArgumentException::class, 'The distinct rule has no wildcard key to compare items.0.sku against');

it('throws for an unknown option', function (): void {
    new Validator()->validate([], ['items.*.sku' => 'distinct:loose']);
})->throws(InvalidArgumentException::class, 'Unknown distinct option: loose (use strict, ignore_case or both)');
