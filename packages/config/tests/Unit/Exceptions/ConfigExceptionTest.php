<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigException;
use Marko\Core\Exceptions\MarkoException;

it('extends MarkoException', function () {
    $exception = new ConfigException('Test message');

    expect($exception)->toBeInstanceOf(MarkoException::class);
});

it('accepts message, context, and suggestion parameters', function () {
    $exception = new ConfigException(
        message: 'Config error occurred',
        context: 'While loading database.php',
        suggestion: 'Check that the file exists',
    );

    expect($exception->getMessage())->toBe('Config error occurred')
        ->and($exception->getContext())->toBe('While loading database.php')
        ->and($exception->getSuggestion())->toBe('Check that the file exists');
});

it('has optional context parameter defaulting to empty string', function () {
    $exception = new ConfigException('Test message');

    expect($exception->getContext())->toBe('');
});

it('has optional suggestion parameter defaulting to empty string', function () {
    $exception = new ConfigException('Test message');

    expect($exception->getSuggestion())->toBe('');
});

it('accepts code and previous exception parameters', function () {
    $previous = new Exception('Previous error');
    $exception = new ConfigException(
        message: 'Config error',
        context: '',
        suggestion: '',
        code: 500,
        previous: $previous,
    );

    expect($exception->getCode())->toBe(500)
        ->and($exception->getPrevious())->toBe($previous);
});
