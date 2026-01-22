<?php

declare(strict_types=1);

use Marko\Log\Exceptions\LogException;

it('stores message correctly', function () {
    $exception = new LogException(
        message: 'Log error occurred',
    );

    expect($exception->getMessage())->toBe('Log error occurred');
});

it('stores context correctly', function () {
    $exception = new LogException(
        message: 'Log error',
        context: 'While writing to file',
    );

    expect($exception->getContext())->toBe('While writing to file');
});

it('stores suggestion correctly', function () {
    $exception = new LogException(
        message: 'Log error',
        suggestion: 'Check file permissions',
    );

    expect($exception->getSuggestion())->toBe('Check file permissions');
});

it('defaults context to empty string', function () {
    $exception = new LogException(
        message: 'Log error',
    );

    expect($exception->getContext())->toBe('');
});

it('defaults suggestion to empty string', function () {
    $exception = new LogException(
        message: 'Log error',
    );

    expect($exception->getSuggestion())->toBe('');
});

it('extends Exception', function () {
    $exception = new LogException(
        message: 'Log error',
    );

    expect($exception)->toBeInstanceOf(Exception::class);
});
