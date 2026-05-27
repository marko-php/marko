<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Errors\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('errors NoDriverException reads from known-drivers.php and includes docs URLs', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())
            ->toContain('marko/errors-simple')
            ->and($exception->getSuggestion())->toContain('marko/errors-advanced')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/errors-simple/')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/errors-advanced/');
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
