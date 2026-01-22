<?php

declare(strict_types=1);

use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Rules\Email;
use Marko\Validation\Rules\Required;
use Marko\Validation\Validation\ValidationErrors;
use Marko\Validation\Validation\Validator;

it('implements ValidatorInterface', function () {
    expect(new Validator())->toBeInstanceOf(ValidatorInterface::class);
});

it('validates with string rules', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['email' => 'invalid'],
        ['email' => 'email'],
    );

    expect($errors->has('email'))->toBeTrue();
});

it('validates with pipe-separated string rules', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['name' => ''],
        ['name' => 'required|min:3'],
    );

    expect($errors->has('name'))->toBeTrue()
        ->and($errors->count())->toBe(1);
});

it('validates with rule objects', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['email' => 'invalid'],
        ['email' => [new Email()]],
    );

    expect($errors->has('email'))->toBeTrue();
});

it('validates with mixed rules', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['name' => 'ab'],
        ['name' => [new Required(), 'min:3']],
    );

    expect($errors->has('name'))->toBeTrue();
});

it('returns empty errors for valid data', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['email' => 'test@example.com'],
        ['email' => 'required|email'],
    );

    expect($errors->isEmpty())->toBeTrue();
});

it('validates multiple fields', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['name' => '', 'email' => 'invalid'],
        [
            'name' => 'required',
            'email' => 'required|email',
        ],
    );

    expect($errors->has('name'))->toBeTrue()
        ->and($errors->has('email'))->toBeTrue();
});

it('passes method returns true for valid data', function () {
    $validator = new Validator();

    $result = $validator->passes(
        ['name' => 'John'],
        ['name' => 'required'],
    );

    expect($result)->toBeTrue();
});

it('passes method returns false for invalid data', function () {
    $validator = new Validator();

    $result = $validator->passes(
        ['name' => ''],
        ['name' => 'required'],
    );

    expect($result)->toBeFalse();
});

it('fails method returns true for invalid data', function () {
    $validator = new Validator();

    $result = $validator->fails(
        ['name' => ''],
        ['name' => 'required'],
    );

    expect($result)->toBeTrue();
});

it('fails method returns false for valid data', function () {
    $validator = new Validator();

    $result = $validator->fails(
        ['name' => 'John'],
        ['name' => 'required'],
    );

    expect($result)->toBeFalse();
});

it('validateOrFail does not throw for valid data', function () {
    $validator = new Validator();

    $validator->validateOrFail(
        ['name' => 'John'],
        ['name' => 'required'],
    );

    expect(true)->toBeTrue();
});

it('validateOrFail throws for invalid data', function () {
    $validator = new Validator();

    $validator->validateOrFail(
        ['name' => ''],
        ['name' => 'required'],
    );
})->throws(ValidationException::class);

it('validateOrFail exception contains errors', function () {
    $validator = new Validator();

    try {
        $validator->validateOrFail(
            ['name' => ''],
            ['name' => 'required'],
        );
    } catch (ValidationException $e) {
        expect($e->errors())->toBeInstanceOf(ValidationErrors::class)
            ->and($e->errors()->has('name'))->toBeTrue();
    }
});

it('skips other rules when value is empty and not required', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['email' => ''],
        ['email' => 'email'],
    );

    expect($errors->isEmpty())->toBeTrue();
});

it('applies all rules when value is present', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['email' => 'ab'],
        ['email' => 'email|min:5'],
    );

    expect($errors->count())->toBe(2);
});

it('handles nullable rule', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['email' => null],
        ['email' => 'nullable|email'],
    );

    expect($errors->isEmpty())->toBeTrue();
});

it('supports dot notation for nested arrays', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['user' => ['email' => 'invalid']],
        ['user.email' => 'email'],
    );

    expect($errors->has('user.email'))->toBeTrue();
});

it('returns empty value for missing nested field', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['user' => ['name' => 'John']],
        ['user.email' => 'required'],
    );

    expect($errors->has('user.email'))->toBeTrue();
});

it('validates with parameterized rules', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['age' => 15],
        ['age' => 'min:18'],
    );

    expect($errors->has('age'))->toBeTrue();
});

it('validates with in rule', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['status' => 'invalid'],
        ['status' => 'in:active,inactive'],
    );

    expect($errors->has('status'))->toBeTrue();
});

it('validates with same rule', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['password' => 'secret', 'password_confirm' => 'different'],
        ['password_confirm' => 'same:password'],
    );

    expect($errors->has('password_confirm'))->toBeTrue();
});

it('validates confirmed rule', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['password' => 'secret', 'password_confirmation' => 'different'],
        ['password' => 'confirmed'],
    );

    expect($errors->has('password'))->toBeTrue();
});

it('passes confirmed rule when confirmation matches', function () {
    $validator = new Validator();

    $errors = $validator->validate(
        ['password' => 'secret', 'password_confirmation' => 'secret'],
        ['password' => 'confirmed'],
    );

    expect($errors->isEmpty())->toBeTrue();
});
