<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigLoadException;

it('extends ConfigException', function () {
    $exception = new ConfigLoadException('/path/to/config.php');

    expect($exception)->toBeInstanceOf(ConfigException::class);
});

it('includes file path in the message', function () {
    $exception = new ConfigLoadException('/path/to/database.php');

    expect($exception->getMessage())->toContain('/path/to/database.php');
});

it('includes parse error details in context when provided', function () {
    $exception = new ConfigLoadException(
        filePath: '/path/to/config.php',
        parseError: 'Unexpected token "}" on line 15',
    );

    expect($exception->getContext())->toContain('Unexpected token "}" on line 15');
});

it('accepts a custom message while still referencing the file path', function () {
    $exception = new ConfigLoadException(
        filePath: '/app/config/app.php',
        parseError: '',
        message: 'File does not exist',
    );

    expect($exception->getMessage())->toContain('/app/config/app.php')
        ->and($exception->getMessage())->toContain('File does not exist');
});

it('stores the file path and provides accessor', function () {
    $exception = new ConfigLoadException('/config/database.php');

    expect($exception->getFilePath())->toBe('/config/database.php');
});

it('provides helpful suggestion for syntax errors', function () {
    $exception = new ConfigLoadException(
        filePath: '/config/app.php',
        parseError: 'syntax error',
    );

    $suggestion = $exception->getSuggestion();

    expect($suggestion)->not->toBeEmpty();
});
