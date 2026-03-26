<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Log\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/log-file', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/log-file');
    });

    it('provides suggestion with composer require command', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/log-file');
    });

    it('includes context about resolving logger interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('logger interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
