<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\Min;

it('implements RuleInterface', function () {
    expect(new Min(3))->toBeInstanceOf(RuleInterface::class);
});

it('passes for string with exact minimum length', function () {
    $rule = new Min(3);

    expect($rule->passes('name', 'abc', []))->toBeTrue();
});

it('passes for string exceeding minimum length', function () {
    $rule = new Min(3);

    expect($rule->passes('name', 'abcdef', []))->toBeTrue();
});

it('fails for string below minimum length', function () {
    $rule = new Min(3);

    expect($rule->passes('name', 'ab', []))->toBeFalse();
});

it('passes for number at minimum', function () {
    $rule = new Min(5);

    expect($rule->passes('age', 5, []))->toBeTrue();
});

it('passes for number above minimum', function () {
    $rule = new Min(5);

    expect($rule->passes('age', 10, []))->toBeTrue();
});

it('fails for number below minimum', function () {
    $rule = new Min(5);

    expect($rule->passes('age', 3, []))->toBeFalse();
});

it('passes for array with minimum items', function () {
    $rule = new Min(2);

    expect($rule->passes('items', ['a', 'b'], []))->toBeTrue();
});

it('fails for array with fewer than minimum items', function () {
    $rule = new Min(2);

    expect($rule->passes('items', ['a'], []))->toBeFalse();
});

it('passes for null value', function () {
    $rule = new Min(3);

    expect($rule->passes('name', null, []))->toBeTrue();
});

it('passes for empty string', function () {
    $rule = new Min(3);

    expect($rule->passes('name', '', []))->toBeTrue();
});

it('handles multibyte strings correctly', function () {
    $rule = new Min(3);

    expect($rule->passes('name', '日本語', []))->toBeTrue()
        ->and($rule->passes('name', '日本', []))->toBeFalse();
});

it('returns correct message for string', function () {
    $rule = new Min(3);

    expect($rule->message('name', 'ab'))->toBe('The name field must be at least 3 characters.');
});

it('returns correct message for array', function () {
    $rule = new Min(3);

    expect($rule->message('items', ['a']))->toBe('The items field must have at least 3 items.');
});

it('returns correct message for number', function () {
    $rule = new Min(10);

    expect($rule->message('age', 5))->toBe('The age field must be at least 10.');
});
