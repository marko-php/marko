<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\In;

it('implements RuleInterface', function () {
    expect(new In('a', 'b', 'c'))->toBeInstanceOf(RuleInterface::class);
});

it('passes for value in list', function () {
    $rule = new In('active', 'inactive', 'pending');

    expect($rule->passes('status', 'active', []))->toBeTrue();
});

it('fails for value not in list', function () {
    $rule = new In('active', 'inactive', 'pending');

    expect($rule->passes('status', 'deleted', []))->toBeFalse();
});

it('uses strict comparison', function () {
    $rule = new In(1, 2, 3);

    expect($rule->passes('count', '1', []))->toBeFalse();
});

it('passes for null value', function () {
    $rule = new In('a', 'b');

    expect($rule->passes('choice', null, []))->toBeTrue();
});

it('passes for empty string', function () {
    $rule = new In('a', 'b');

    expect($rule->passes('choice', '', []))->toBeTrue();
});

it('returns correct error message', function () {
    $rule = new In('small', 'medium', 'large');

    expect($rule->message('size', 'huge'))->toBe("The size field must be one of: 'small', 'medium', 'large'.");
});

it('formats numeric values in message', function () {
    $rule = new In(1, 2, 3);

    expect($rule->message('count', 5))->toBe('The count field must be one of: 1, 2, 3.');
});
