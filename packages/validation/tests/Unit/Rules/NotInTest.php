<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\NotIn;

it('implements RuleInterface', function () {
    expect(new NotIn('a', 'b'))->toBeInstanceOf(RuleInterface::class);
});

it('rejects a numeric string present in a numeric disallow-list for the NotIn rule', function () {
    $rule = new NotIn(1, 2, 3);

    expect($rule->passes('count', '1', []))->toBeFalse()
        ->and($rule->passes('count', '4', []))->toBeTrue();
});
