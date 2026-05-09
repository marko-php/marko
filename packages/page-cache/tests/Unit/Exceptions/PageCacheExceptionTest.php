<?php

declare(strict_types=1);

use Marko\PageCache\Exceptions\PageCacheException;

it('constructs a base PageCacheException with message, context, and suggestion', function (): void {
    $exception = new PageCacheException(
        message: 'Test error',
        context: 'test context',
        suggestion: 'try this',
    );

    expect($exception->getMessage())->toBe('Test error')
        ->and($exception->getContext())->toBe('test context')
        ->and($exception->getSuggestion())->toBe('try this');
});

it('exposes context and suggestion via getter methods', function (): void {
    $exception = new PageCacheException(
        message: 'Test error',
        context: 'specific context',
        suggestion: 'specific suggestion',
    );

    expect($exception->getContext())->toBe('specific context')
        ->and($exception->getSuggestion())->toBe('specific suggestion');
});
