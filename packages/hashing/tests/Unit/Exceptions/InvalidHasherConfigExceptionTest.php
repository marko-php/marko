<?php

declare(strict_types=1);

use Marko\Hashing\Exceptions\HasherException;
use Marko\Hashing\Exceptions\InvalidHasherConfigException;

it('extends HasherException', function () {
    $exception = new InvalidHasherConfigException(
        message: 'Invalid configuration',
    );

    expect($exception)->toBeInstanceOf(HasherException::class);
});

it('stores message correctly', function () {
    $exception = new InvalidHasherConfigException(
        message: 'Invalid bcrypt cost',
    );

    expect($exception->getMessage())->toBe('Invalid bcrypt cost');
});

it('stores context correctly', function () {
    $exception = new InvalidHasherConfigException(
        message: 'Invalid configuration',
        context: 'Cost must be between 4 and 31',
    );

    expect($exception->getContext())->toBe('Cost must be between 4 and 31');
});

it('stores suggestion correctly', function () {
    $exception = new InvalidHasherConfigException(
        message: 'Invalid configuration',
        suggestion: 'Update your config file',
    );

    expect($exception->getSuggestion())->toBe('Update your config file');
});
