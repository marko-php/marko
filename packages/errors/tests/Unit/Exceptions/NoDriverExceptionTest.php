<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Errors\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/errors-advanced and marko/errors-simple', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/errors-advanced')
            ->and($constant->getValue())->toContain('marko/errors-simple');
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())
            ->toContain('composer require marko/errors-advanced')
            ->and($exception->getSuggestion())->toContain('composer require marko/errors-simple');
    });

    it('includes context about resolving error handler interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('error handler interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
