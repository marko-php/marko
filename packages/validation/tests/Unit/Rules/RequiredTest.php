<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\Required;

it('implements RuleInterface', function () {
    expect(new Required())->toBeInstanceOf(RuleInterface::class);
});

it('fails for null value', function () {
    $rule = new Required();

    expect($rule->passes('name', null, []))->toBeFalse();
});

it('fails for empty string', function () {
    $rule = new Required();

    expect($rule->passes('name', '', []))->toBeFalse();
});

it('fails for whitespace only string', function () {
    $rule = new Required();

    expect($rule->passes('name', '   ', []))->toBeFalse();
});

it('fails for empty array', function () {
    $rule = new Required();

    expect($rule->passes('items', [], []))->toBeFalse();
});

it('passes for non-empty string', function () {
    $rule = new Required();

    expect($rule->passes('name', 'John', []))->toBeTrue();
});

it('passes for zero', function () {
    $rule = new Required();

    expect($rule->passes('count', 0, []))->toBeTrue();
});

it('passes for string zero', function () {
    $rule = new Required();

    expect($rule->passes('count', '0', []))->toBeTrue();
});

it('passes for non-empty array', function () {
    $rule = new Required();

    expect($rule->passes('items', ['a'], []))->toBeTrue();
});

it('passes for false', function () {
    $rule = new Required();

    expect($rule->passes('active', false, []))->toBeTrue();
});

it('returns correct error message', function () {
    $rule = new Required();

    expect($rule->message('name', null))->toBe('The name field is required.');
});
