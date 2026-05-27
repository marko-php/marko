<?php

declare(strict_types=1);

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

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
