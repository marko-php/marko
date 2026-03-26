<?php

declare(strict_types=1);

use Marko\Cache\Exceptions\NoDriverException;
use Marko\Core\Exceptions\MarkoException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/cache-array, marko/cache-file, and marko/cache-redis', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/cache-array')
            ->and($constant->getValue())->toContain('marko/cache-file')
            ->and($constant->getValue())->toContain('marko/cache-redis');
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
