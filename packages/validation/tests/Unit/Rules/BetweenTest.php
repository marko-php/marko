<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\Between;

it('implements RuleInterface', function () {
    expect(new Between(1, 10))->toBeInstanceOf(RuleInterface::class);
});

it('passes for string within range', function () {
    $rule = new Between(3, 5);

    expect($rule->passes('name', 'abcd', []))->toBeTrue();
});

it('passes for string at minimum', function () {
    $rule = new Between(3, 5);

    expect($rule->passes('name', 'abc', []))->toBeTrue();
});

it('passes for string at maximum', function () {
    $rule = new Between(3, 5);

    expect($rule->passes('name', 'abcde', []))->toBeTrue();
});

it('fails for string below range', function () {
    $rule = new Between(3, 5);

    expect($rule->passes('name', 'ab', []))->toBeFalse();
});

it('fails for string above range', function () {
    $rule = new Between(3, 5);

    expect($rule->passes('name', 'abcdef', []))->toBeFalse();
});

it('passes for number within range', function () {
    $rule = new Between(1, 100);

    expect($rule->passes('age', 50, []))->toBeTrue();
});

it('passes for number at boundaries', function () {
    $rule = new Between(1, 100);

    expect($rule->passes('age', 1, []))->toBeTrue()
        ->and($rule->passes('age', 100, []))->toBeTrue();
});

it('fails for number outside range', function () {
    $rule = new Between(1, 100);

    expect($rule->passes('age', 0, []))->toBeFalse()
        ->and($rule->passes('age', 101, []))->toBeFalse();
});

it('passes for array within range', function () {
    $rule = new Between(2, 4);

    expect($rule->passes('items', ['a', 'b', 'c'], []))->toBeTrue();
});

it('passes for null value', function () {
    $rule = new Between(1, 10);

    expect($rule->passes('name', null, []))->toBeTrue();
});

it('passes for empty string', function () {
    $rule = new Between(1, 10);

    expect($rule->passes('name', '', []))->toBeTrue();
});

it('returns correct message for string', function () {
    $rule = new Between(3, 5);

    expect($rule->message('name', 'ab'))->toBe('The name field must be between 3 and 5 characters.');
});

it('returns correct message for array', function () {
    $rule = new Between(2, 4);

    expect($rule->message('items', ['a']))->toBe('The items field must have between 2 and 4 items.');
});

it('returns correct message for number', function () {
    $rule = new Between(1, 100);

    expect($rule->message('age', 0))->toBe('The age field must be between 1 and 100.');
});
