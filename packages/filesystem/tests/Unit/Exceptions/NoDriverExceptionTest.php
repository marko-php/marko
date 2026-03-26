<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Filesystem\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/filesystem-local and marko/filesystem-s3', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/filesystem-local')
            ->and($constant->getValue())->toContain('marko/filesystem-s3');
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/filesystem-local')
            ->and($exception->getSuggestion())->toContain('composer require marko/filesystem-s3');
    });

    it('includes context about resolving filesystem interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('filesystem interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
