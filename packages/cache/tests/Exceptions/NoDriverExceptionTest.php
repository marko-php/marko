<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Exceptions\NoDriverException;
use Marko\Core\Exceptions\MarkoException;

describe('NoDriverException', function (): void {
    it('cache NoDriverException reads from known-drivers.php and includes docs URLs', function (): void {
        $exception = NoDriverException::noDriverInstalled();
        $suggestion = $exception->getSuggestion();

        expect($suggestion)
            ->toContain('marko/cache-file')
            ->and($suggestion)->toContain('marko/cache-redis')
            ->and($suggestion)->toContain('marko/cache-array')
            ->and($suggestion)->toContain('https://marko.build/docs/packages/cache-file/')
            ->and($suggestion)->toContain('https://marko.build/docs/packages/cache-redis/')
            ->and($suggestion)->toContain('https://marko.build/docs/packages/cache-array/');
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())
            ->toContain('composer require marko/cache-array')
            ->and($exception->getSuggestion())->toContain('composer require marko/cache-file')
            ->and($exception->getSuggestion())->toContain('composer require marko/cache-redis');
    });

    it('includes context about resolving cache interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('cache interface');
    });

    it('names the lock store and the driver packages when LockStoreInterface is unbound', function (): void {
        $exception = NoDriverException::noDriverInstalled(LockStoreInterface::class);

        expect($exception->getMessage())->toBe('No cache lock store installed.')
            ->and($exception->getContext())->toContain('LockStoreInterface')
            ->and($exception->getSuggestion())->toContain('composer require marko/cache-redis')
            ->and($exception->getSuggestion())->toContain('Only marko/cache-redis excludes across hosts');
    });

    it('keeps the cache driver message for any other cache interface', function (): void {
        expect(NoDriverException::noDriverInstalled(CacheInterface::class)->getMessage())
            ->toBe('No cache driver installed.');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
