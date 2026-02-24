<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Pagination\Exceptions\PaginationException;

it('extends MarkoException', function () {
    $exception = new PaginationException('Test error');

    expect($exception)->toBeInstanceOf(MarkoException::class);
});

it('stores message correctly', function () {
    $exception = new PaginationException('Test pagination error');

    expect($exception->getMessage())->toBe('Test pagination error');
});

it('stores context correctly', function () {
    $exception = new PaginationException('Test error', 'test context');

    expect($exception->getContext())->toBe('test context');
});

it('stores suggestion correctly', function () {
    $exception = new PaginationException('Test error', 'context', 'try this');

    expect($exception->getSuggestion())->toBe('try this');
});

it('has empty context by default', function () {
    $exception = new PaginationException('Test error');

    expect($exception->getContext())->toBe('');
});

it('has empty suggestion by default', function () {
    $exception = new PaginationException('Test error');

    expect($exception->getSuggestion())->toBe('');
});

it('creates invalidPage exception with helpful message', function () {
    $exception = PaginationException::invalidPage(0);

    expect($exception)->toBeInstanceOf(PaginationException::class)
        ->and($exception->getMessage())->toContain('0')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('creates invalidPerPage exception with helpful message', function () {
    $exception = PaginationException::invalidPerPage(0);

    expect($exception)->toBeInstanceOf(PaginationException::class)
        ->and($exception->getMessage())->toContain('0')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('creates invalidCursor exception with helpful message', function () {
    $exception = PaginationException::invalidCursor('bad-cursor');

    expect($exception)->toBeInstanceOf(PaginationException::class)
        ->and($exception->getMessage())->toContain('cursor')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
