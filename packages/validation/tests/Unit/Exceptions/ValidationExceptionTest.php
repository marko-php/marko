<?php

declare(strict_types=1);

use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Validation\ValidationErrors;

it('stores errors correctly', function () {
    $errors = new ValidationErrors();
    $errors->add('email', 'Invalid email');

    $exception = new ValidationException('Validation failed', $errors);

    expect($exception->errors())->toBe($errors);
});

it('stores message correctly', function () {
    $errors = new ValidationErrors();
    $exception = new ValidationException('Custom message', $errors);

    expect($exception->getMessage())->toBe('Custom message');
});

it('stores context correctly', function () {
    $errors = new ValidationErrors();
    $exception = new ValidationException('Message', $errors, 'Test context');

    expect($exception->getContext())->toBe('Test context');
});

it('stores suggestion correctly', function () {
    $errors = new ValidationErrors();
    $exception = new ValidationException('Message', $errors, '', 'Try again');

    expect($exception->getSuggestion())->toBe('Try again');
});

it('has empty context by default', function () {
    $errors = new ValidationErrors();
    $exception = new ValidationException('Message', $errors);

    expect($exception->getContext())->toBe('');
});

it('has empty suggestion by default', function () {
    $errors = new ValidationErrors();
    $exception = new ValidationException('Message', $errors);

    expect($exception->getSuggestion())->toBe('');
});

it('creates exception with errors using factory method', function () {
    $errors = new ValidationErrors();
    $errors->add('name', 'Name is required');

    $exception = ValidationException::withErrors($errors);

    expect($exception->getMessage())->toBe('The given data was invalid.')
        ->and($exception->errors())->toBe($errors)
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
