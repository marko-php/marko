<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Encryption\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('encryption NoDriverException reads from known-drivers.php and includes docs URL', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())
            ->toContain('marko/encryption-openssl')
            ->and($exception->getSuggestion())->toContain('composer require marko/encryption-openssl')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/encryption-openssl/');
    });

    it('provides suggestion with composer require command', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/encryption-openssl');
    });

    it('includes context about resolving encryption interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('encryption interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
