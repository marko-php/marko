<?php

declare(strict_types=1);

use Marko\Authentication\Exceptions\NoDriverException;
use Marko\Core\Exceptions\MarkoException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/authentication-token', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/authentication-token');
    });

    it('provides suggestion with composer require command', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/authentication-token');
    });

    it('includes context about resolving authentication interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('authentication interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
