<?php

declare(strict_types=1);

use Marko\Session\Exceptions\InvalidSessionIdException;
use Marko\Session\Exceptions\SessionException;

it('extends SessionException', function () {
    $exception = InvalidSessionIdException::forId('bad-id');

    expect($exception)->toBeInstanceOf(SessionException::class);
});

it('creates exception with message', function () {
    $exception = InvalidSessionIdException::forId('xyz');

    expect($exception->getMessage())->toBe('Invalid session ID format');
});

it('includes session ID in context', function () {
    $exception = InvalidSessionIdException::forId('my-invalid-id');

    expect($exception->getContext())->toBe('Provided session ID: my-invalid-id');
});

it('includes suggestion', function () {
    $exception = InvalidSessionIdException::forId('short');

    expect($exception->getSuggestion())->toContain('32-128 characters');
});
