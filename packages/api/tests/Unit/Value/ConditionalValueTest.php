<?php

declare(strict_types=1);

use Marko\Api\Value\ConditionalValue;
use Marko\Api\Value\MissingValue;

it('provides ConditionalValue class that wraps a value with a boolean condition', function () {
    $conditional = new ConditionalValue(true, 'hello');

    expect($conditional)->toBeInstanceOf(ConditionalValue::class)
        ->and($conditional->condition)->toBeTrue()
        ->and($conditional->value)->toBe('hello');
});

it('stores false condition correctly', function () {
    $conditional = new ConditionalValue(false, 'hello');

    expect($conditional->condition)->toBeFalse()
        ->and($conditional->value)->toBe('hello');
});

it('resolves to the value when condition is true', function () {
    $conditional = new ConditionalValue(true, 'hello');

    expect($conditional->resolve())->toBe('hello');
});

it('resolves to MissingValue when condition is false', function () {
    $conditional = new ConditionalValue(false, 'hello');

    expect($conditional->resolve())->toBeInstanceOf(MissingValue::class);
});
