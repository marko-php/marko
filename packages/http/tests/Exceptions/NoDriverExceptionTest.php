<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Http\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('http NoDriverException reads from known-drivers.php and includes docs URL', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())
            ->toContain('marko/http-guzzle')
            ->toContain('https://marko.build/docs/packages/http-guzzle/');
    });

    it('provides suggestion with composer require command', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/http-guzzle');
    });

    it('includes context about resolving HTTP client interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('HTTP client interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
