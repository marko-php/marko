<?php

declare(strict_types=1);

use Marko\Hashing\Exceptions\HasherException;

it('stores message correctly', function () {
    $exception = new HasherException(
        message: 'Test error message',
    );

    expect($exception->getMessage())->toBe('Test error message');
});

it('stores context correctly', function () {
    $exception = new HasherException(
        message: 'Test error',
        context: 'While processing hash',
    );

    expect($exception->getContext())->toBe('While processing hash');
});

it('stores suggestion correctly', function () {
    $exception = new HasherException(
        message: 'Test error',
        context: '',
        suggestion: 'Try a different approach',
    );

    expect($exception->getSuggestion())->toBe('Try a different approach');
});

it('defaults context to empty string', function () {
    $exception = new HasherException(
        message: 'Test error',
    );

    expect($exception->getContext())->toBe('');
});

it('defaults suggestion to empty string', function () {
    $exception = new HasherException(
        message: 'Test error',
    );

    expect($exception->getSuggestion())->toBe('');
});

it('extends Exception', function () {
    $exception = new HasherException(
        message: 'Test error',
    );

    expect($exception)->toBeInstanceOf(Exception::class);
});

it('stores code correctly', function () {
    $exception = new HasherException(
        message: 'Test error',
        code: 500,
    );

    expect($exception->getCode())->toBe(500);
});

it('stores previous exception correctly', function () {
    $previous = new Exception('Previous error');
    $exception = new HasherException(
        message: 'Test error',
        previous: $previous,
    );

    expect($exception->getPrevious())->toBe($previous);
});
