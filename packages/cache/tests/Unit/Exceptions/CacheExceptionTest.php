<?php

declare(strict_types=1);

use Marko\Cache\Exceptions\CacheException;

it('stores message correctly', function () {
    $exception = new CacheException('Test error');

    expect($exception->getMessage())->toBe('Test error');
});

it('stores context correctly', function () {
    $exception = new CacheException('Test error', 'test context');

    expect($exception->getContext())->toBe('test context');
});

it('stores suggestion correctly', function () {
    $exception = new CacheException('Test error', 'context', 'try this');

    expect($exception->getSuggestion())->toBe('try this');
});

it('stores code correctly', function () {
    $exception = new CacheException('Test error', '', '', 123);

    expect($exception->getCode())->toBe(123);
});

it('stores previous exception correctly', function () {
    $previous = new Exception('Previous error');
    $exception = new CacheException('Test error', '', '', 0, $previous);

    expect($exception->getPrevious())->toBe($previous);
});

it('has empty context by default', function () {
    $exception = new CacheException('Test error');

    expect($exception->getContext())->toBe('');
});

it('has empty suggestion by default', function () {
    $exception = new CacheException('Test error');

    expect($exception->getSuggestion())->toBe('');
});
