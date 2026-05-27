<?php

declare(strict_types=1);

use Marko\PageCache\Exceptions\NoDriverException;
use Marko\PageCache\Exceptions\PageCacheException;

it('page-cache NoDriverException reads from known-drivers.php and includes docs URL', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getSuggestion())
        ->toContain('marko/page-cache-file')
        ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/page-cache-file/');
});

it('page-cache NoDriverException exposes a noDriverInstalled() factory (renamed from noBinding for consistency)', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception)->toBeInstanceOf(NoDriverException::class)
        ->and($exception)->toBeInstanceOf(PageCacheException::class);
});
