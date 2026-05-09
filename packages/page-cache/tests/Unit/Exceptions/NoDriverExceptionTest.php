<?php

declare(strict_types=1);

use Marko\PageCache\Exceptions\NoDriverException;
use Marko\PageCache\Exceptions\PageCacheException;

it('produces a NoDriverException via static factory with helpful message and suggestion', function (): void {
    $exception = NoDriverException::noBinding();

    expect($exception->getMessage())->not->toBeEmpty()
        ->and($exception->getSuggestion())->toContain('marko/page-cache-');
});

it('inherits NoDriverException from PageCacheException', function (): void {
    $exception = NoDriverException::noBinding();

    expect($exception)->toBeInstanceOf(NoDriverException::class)
        ->and($exception)->toBeInstanceOf(PageCacheException::class);
});
