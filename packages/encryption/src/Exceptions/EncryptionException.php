<?php

declare(strict_types=1);

namespace Marko\Encryption\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class EncryptionException extends MarkoException
{
    public static function nonAeadCipher(
        string $cipher,
    ): self {
        return new self(
            message: "Cipher '$cipher' is not an AEAD mode",
            context: 'Validating encryption cipher at construction',
            suggestion: 'Use an AEAD cipher such as aes-256-gcm or aes-128-gcm',
        );
    }

    public static function invalidKeyLength(
        string $cipher,
        int $expectedLength,
    ): self {
        return new self(
            message: 'Invalid encryption key',
            context: "The ENCRYPTION_KEY must be a base64-encoded $expectedLength-byte key for cipher '$cipher'",
            suggestion: "Generate a key with: base64_encode(random_bytes($expectedLength))",
        );
    }

    public static function invalidPreviousKey(
        int $index,
        string $cipher,
        int $expectedLength,
    ): self {
        return new self(
            message: "Invalid previous encryption key at index $index",
            context: "Every entry in encryption.previous_keys must be a base64-encoded $expectedLength-byte key for cipher '$cipher'",
            suggestion: 'Remove the entry, or fix it to the exact base64 key that was used before rotation',
        );
    }

    public static function invalidPreviousKeys(): self
    {
        return new self(
            message: 'Invalid encryption.previous_keys configuration',
            context: 'encryption.previous_keys must be a list of base64-encoded key strings',
            suggestion: 'Set ENCRYPTION_PREVIOUS_KEYS to a comma-separated list of base64 keys, or leave it empty',
        );
    }

    public static function invalidCipher(
        string $cipher,
    ): self {
        return new self(
            message: "Cipher '$cipher' is not recognized by OpenSSL",
            context: 'Validating encryption cipher at construction',
            suggestion: 'Use a valid OpenSSL cipher such as aes-256-gcm',
        );
    }
}
