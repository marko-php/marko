<?php

declare(strict_types=1);

use Marko\Session\Exceptions\SessionException;

it('stores message correctly', function () {
    $exception = new SessionException('Test error');

    expect($exception->getMessage())->toBe('Test error');
});

it('stores context correctly', function () {
    $exception = new SessionException('Test error', 'test context');

    expect($exception->getContext())->toBe('test context');
});

it('stores suggestion correctly', function () {
    $exception = new SessionException('Test error', 'context', 'try this');

    expect($exception->getSuggestion())->toBe('try this');
});

it('has empty context by default', function () {
    $exception = new SessionException('Test error');

    expect($exception->getContext())->toBe('');
});

it('has empty suggestion by default', function () {
    $exception = new SessionException('Test error');

    expect($exception->getSuggestion())->toBe('');
});
