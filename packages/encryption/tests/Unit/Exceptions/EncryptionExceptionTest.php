<?php

declare(strict_types=1);

use Marko\Encryption\Exceptions\EncryptionException;

describe('EncryptionException', function (): void {
    it('stores message correctly', function (): void {
        $exception = new EncryptionException('Test error');

        expect($exception->getMessage())->toBe('Test error');
    });

    it('stores context correctly', function (): void {
        $exception = new EncryptionException('Test error', 'test context');

        expect($exception->getContext())->toBe('test context');
    });

    it('stores suggestion correctly', function (): void {
        $exception = new EncryptionException('Test error', 'context', 'try this');

        expect($exception->getSuggestion())->toBe('try this');
    });

    it('stores code correctly', function (): void {
        $exception = new EncryptionException('Test error', '', '', 123);

        expect($exception->getCode())->toBe(123);
    });

    it('stores previous exception correctly', function (): void {
        $previous = new Exception('Previous error');
        $exception = new EncryptionException('Test error', '', '', 0, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('has empty context by default', function (): void {
        $exception = new EncryptionException('Test error');

        expect($exception->getContext())->toBe('');
    });

    it('has empty suggestion by default', function (): void {
        $exception = new EncryptionException('Test error');

        expect($exception->getSuggestion())->toBe('');
    });
});
