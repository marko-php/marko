<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Log\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('log NoDriverException reads from known-drivers.php and includes docs URL', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('marko/log-file')
            ->and($exception->getSuggestion())->toContain('composer require marko/log-file')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/log-file/');
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
