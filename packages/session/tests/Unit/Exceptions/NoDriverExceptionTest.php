<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Session\Exceptions\NoDriverException;

it('has DRIVER_PACKAGES constant listing marko/session-database and marko/session-file', function () {
    $reflection = new ReflectionClass(NoDriverException::class);
    $constant = $reflection->getConstant('DRIVER_PACKAGES');

    expect($constant)->toBe([
        'marko/session-database',
        'marko/session-file',
    ]);
});

it('provides suggestion with composer require commands for all driver packages', function () {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getSuggestion())
        ->toContain('composer require marko/session-database')
        ->and($exception->getSuggestion())
        ->toContain('composer require marko/session-file');
});

it('includes context about resolving session interfaces', function () {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getContext())->toContain('session interface');
});

it('extends MarkoException', function () {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception)->toBeInstanceOf(MarkoException::class);
});
