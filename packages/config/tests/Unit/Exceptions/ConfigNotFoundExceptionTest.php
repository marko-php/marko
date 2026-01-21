<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigNotFoundException;

it('extends ConfigException', function () {
    $exception = new ConfigNotFoundException('database.host');

    expect($exception)->toBeInstanceOf(ConfigException::class);
});

it('includes the requested key in the message', function () {
    $exception = new ConfigNotFoundException('database.host');

    expect($exception->getMessage())->toContain('database.host');
});

it('provides a helpful suggestion to check config files or add default value', function () {
    $exception = new ConfigNotFoundException('database.host');

    $suggestion = $exception->getSuggestion();

    expect($suggestion)->toContain('config')
        ->and($suggestion)->toContain('default');
});

it('accepts a custom message while still referencing the key', function () {
    $exception = new ConfigNotFoundException(
        key: 'app.debug',
        message: 'Required configuration is missing',
    );

    expect($exception->getMessage())->toContain('app.debug')
        ->and($exception->getMessage())->toContain('Required configuration is missing');
});
