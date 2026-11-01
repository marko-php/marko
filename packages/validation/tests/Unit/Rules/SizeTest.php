<?php

declare(strict_types=1);

use Marko\Validation\Contracts\NumericAwareRuleInterface;
use Marko\Validation\Rules\Size;
use Marko\Validation\Tests\Support\TestUploads;
use Marko\Validation\Validation\Validator;

it('is a numeric-aware size rule', function (): void {
    expect(new Size(3))->toBeInstanceOf(NumericAwareRuleInterface::class);
});

it('measures a string by its length', function (): void {
    $rule = new Size(5);

    expect($rule->passes('code', 'abcde', []))->toBeTrue()
        ->and($rule->passes('code', 'äbcdé', []))->toBeTrue()
        ->and($rule->passes('code', 'abcd', []))->toBeFalse()
        ->and($rule->message('code', 'abcd'))->toBe('The code field must be exactly 5 characters.');
});

it('measures a numeric string by length unless the rule is numeric', function (): void {
    expect(new Size(2)->passes('qty', '10', []))->toBeTrue()
        ->and(new Size(10)->passes('qty', '10', []))->toBeFalse()
        ->and(new Size(10)->asNumeric()->passes('qty', '10', []))->toBeTrue()
        ->and(new Size(10)->asNumeric()->message('qty', '9'))->toBe('The qty field must be 10.');
});

it('compares an int or float by value', function (): void {
    $rule = new Size(10);

    expect($rule->passes('qty', 10, []))->toBeTrue()
        ->and($rule->passes('qty', 10.0, []))->toBeTrue()
        ->and($rule->passes('qty', 11, []))->toBeFalse()
        ->and($rule->message('qty', 11))->toBe('The qty field must be 10.');
});

it('counts the items of an array', function (): void {
    $rule = new Size(2);

    expect($rule->passes('tags', ['a', 'b'], []))->toBeTrue()
        ->and($rule->passes('tags', ['a'], []))->toBeFalse()
        ->and($rule->message('tags', ['a']))->toBe('The tags field must have exactly 2 items.');
});

it('fails an uploaded file with a message pointing to min_size and max_size', function (): void {
    $rule = new Size(2048);
    $file = TestUploads::sized(2048);

    expect($rule->passes('avatar', $file, []))->toBeFalse()
        ->and($rule->message('avatar', $file))
        ->toBe('The avatar field is a file: use min_size:2048|max_size:2048 to require its size in kilobytes.');
});

it('compares by value under integer|size and by length under string|size', function (): void {
    $validator = new Validator();

    expect($validator->passes(['qty' => '10'], ['qty' => 'integer|size:10']))->toBeTrue()
        ->and($validator->passes(['pin' => '10'], ['pin' => 'string|size:10']))->toBeFalse()
        ->and($validator->validate(['pin' => '10'], ['pin' => 'string|size:10'])->get('pin'))
        ->toBe(['The pin field must be exactly 10 characters.']);
});
