<?php

declare(strict_types=1);

use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Encryption\Exceptions\EncryptionException;

describe('DecryptionException', function (): void {
    it('extends EncryptionException', function (): void {
        $exception = DecryptionException::invalidPayload();

        expect($exception)->toBeInstanceOf(EncryptionException::class);
    });

    it('creates invalidPayload exception with correct message', function (): void {
        $exception = DecryptionException::invalidPayload();

        expect($exception->getMessage())->toBe('The encrypted payload is invalid')
            ->and($exception->getContext())->toBe('Decrypting data that may be corrupted or tampered with')
            ->and($exception->getSuggestion())->toBe('Verify the data has not been modified after encryption');
    });

    it('creates invalidKey exception with correct message', function (): void {
        $exception = DecryptionException::invalidKey();

        expect($exception->getMessage())->toBe('The encryption key is invalid or does not match')
            ->and($exception->getContext())->toBe('Decrypting data with a different key than was used for encryption')
            ->and($exception->getSuggestion())->toBe(
                'Ensure the same ENCRYPTION_KEY is used for both encryption and decryption',
            );
    });
});
