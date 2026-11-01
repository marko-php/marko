<?php

declare(strict_types=1);

use Marko\Authentication\Validation\Password;
use Marko\Validation\Contracts\MultiMessageRuleInterface;
use Marko\Validation\Validation\Validator;

function basePassword(): Password
{
    return new Password(
        min: 1,
        mixedCase: false,
        letters: false,
        numbers: false,
        symbols: false,
    );
}

it('is a multi-message validation rule', function (): void {
    expect(basePassword())->toBeInstanceOf(MultiMessageRuleInterface::class);
});

it('passes and fails each requirement with its own message', function (
    Closure $configure,
    string $passing,
    string $failing,
    string $message,
): void {
    $rule = $configure(basePassword());

    expect($rule->passes('password', $passing, []))->toBeTrue()
        ->and($rule->passes('password', $failing, []))->toBeFalse()
        ->and($rule->messages('password', $failing))->toBe([$message])
        ->and($rule->message('password', $failing))->toBe($message);
})->with([
    'min' => [
        fn (Password $rule): Password => $rule->min(8),
        'longenough',
        'short',
        'The password field must be at least 8 characters.',
    ],
    'mixed case' => [
        fn (Password $rule): Password => $rule->mixedCase(),
        'Secret',
        'secret',
        'The password field must contain at least one uppercase and one lowercase letter.',
    ],
    'letters' => [
        fn (Password $rule): Password => $rule->letters(),
        '1234a',
        '12345',
        'The password field must contain at least one letter.',
    ],
    'numbers' => [
        fn (Password $rule): Password => $rule->numbers(),
        'secret1',
        'secret',
        'The password field must contain at least one number.',
    ],
    'symbols' => [
        fn (Password $rule): Password => $rule->symbols(),
        'secret!',
        'secret',
        'The password field must contain at least one symbol.',
    ],
]);

it('counts characters rather than bytes for the minimum length', function (): void {
    $rule = basePassword()->min(4);

    expect($rule->passes('password', 'ñññ', []))->toBeFalse()
        ->and($rule->passes('password', 'ññññ', []))->toBeTrue();
});

it('recognizes non-ASCII letters and cases', function (): void {
    $rule = basePassword()->mixedCase()->letters();

    expect($rule->passes('password', 'Ñandú', []))->toBeTrue()
        ->and($rule->passes('password', 'ñandú', []))->toBeFalse();
});

it('reports every failed requirement in order', function (): void {
    $rule = basePassword()->min(10)->mixedCase()->numbers()->symbols();

    expect($rule->messages('password', 'short'))->toBe([
        'The password field must be at least 10 characters.',
        'The password field must contain at least one uppercase and one lowercase letter.',
        'The password field must contain at least one number.',
        'The password field must contain at least one symbol.',
    ]);
});

it('adds each failed requirement as its own validator error', function (): void {
    $rule = basePassword()->min(10)->numbers();

    $errors = new Validator()->validate(['password' => 'short'], ['password' => ['required', $rule]]);

    expect($errors->get('password'))->toBe([
        'The password field must be at least 10 characters.',
        'The password field must contain at least one number.',
    ]);
});

it('fails a non-string value with a single message', function (): void {
    $rule = basePassword()->min(8)->numbers();

    expect($rule->passes('password', ['secret'], []))->toBeFalse()
        ->and($rule->messages('password', ['secret']))->toBe(['The password field must be a string.']);
});

it('fails a string that is not valid UTF-8', function (): void {
    $rule = basePassword();

    expect($rule->passes('password', "\xB1\x31", []))->toBeFalse()
        ->and($rule->messages('password', "\xB1\x31"))->toBe(['The password field must be valid UTF-8 text.']);
});

it('returns a new rule from each builder call without changing the original', function (): void {
    $defaults = basePassword();

    $strict = $defaults->min(16)->mixedCase()->letters()->numbers()->symbols();

    expect($strict)->not->toBe($defaults)
        ->and($defaults->passes('password', 'a', []))->toBeTrue()
        ->and($strict->passes('password', 'a', []))->toBeFalse();
});

it('rejects a minimum length below one', function (): void {
    expect(fn () => basePassword()->min(0))
        ->toThrow(InvalidArgumentException::class, 'Password minimum length must be at least 1, got 0');
});
