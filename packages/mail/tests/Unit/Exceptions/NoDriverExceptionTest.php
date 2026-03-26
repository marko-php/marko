<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Mail\Exceptions\NoDriverException;

test('it has DRIVER_PACKAGES constant listing marko/mail-log and marko/mail-smtp', function () {
    $reflection = new ReflectionClass(NoDriverException::class);
    $constants = $reflection->getConstants();

    expect($constants)->toHaveKey('DRIVER_PACKAGES')
        ->and($constants['DRIVER_PACKAGES'])->toBe([
            'marko/mail-log',
            'marko/mail-smtp',
        ]);
});

test('it provides suggestion with composer require commands for all driver packages', function () {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getSuggestion())->toContain('composer require marko/mail-log')
        ->and($exception->getSuggestion())->toContain('composer require marko/mail-smtp');
});

test('it includes context about resolving mail interfaces', function () {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getContext())->toBe('Attempted to resolve a mail interface but no implementation is bound.');
});

test('it extends MarkoException', function () {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception)->toBeInstanceOf(MarkoException::class);
});
