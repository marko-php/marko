<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\Numeric;

it('implements RuleInterface', function () {
    expect(new Numeric())->toBeInstanceOf(RuleInterface::class);
});

it('passes for integer', function () {
    $rule = new Numeric();

    expect($rule->passes('age', 25, []))->toBeTrue();
});

it('passes for float', function () {
    $rule = new Numeric();

    expect($rule->passes('price', 19.99, []))->toBeTrue();
});

it('passes for numeric string', function () {
    $rule = new Numeric();

    expect($rule->passes('count', '123', []))->toBeTrue();
});

it('passes for negative number', function () {
    $rule = new Numeric();

    expect($rule->passes('balance', -50, []))->toBeTrue();
});

it('passes for scientific notation', function () {
    $rule = new Numeric();

    expect($rule->passes('value', '1.5e10', []))->toBeTrue();
});

it('fails for non-numeric string', function () {
    $rule = new Numeric();

    expect($rule->passes('value', 'abc', []))->toBeFalse();
});

it('fails for array', function () {
    $rule = new Numeric();

    expect($rule->passes('value', [1, 2, 3], []))->toBeFalse();
});

it('passes for null value', function () {
    $rule = new Numeric();

    expect($rule->passes('value', null, []))->toBeTrue();
});

it('passes for empty string', function () {
    $rule = new Numeric();

    expect($rule->passes('value', '', []))->toBeTrue();
});

it('returns correct error message', function () {
    $rule = new Numeric();

    expect($rule->message('age', 'abc'))->toBe('The age field must be a number.');
});
