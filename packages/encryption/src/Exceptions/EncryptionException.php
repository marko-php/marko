<?php

declare(strict_types=1);

namespace Marko\Encryption\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class EncryptionException extends MarkoException
{
    public static function nonAeadCipher(string $cipher): self
    {
        return new self(
            message: "Cipher '$cipher' is not an AEAD mode",
            context: 'Validating encryption cipher at construction',
            suggestion: 'Use an AEAD cipher such as aes-256-gcm or aes-128-gcm',
        );
    }

    public static function invalidCipher(string $cipher): self
    {
        return new self(
            message: "Cipher '$cipher' is not recognized by OpenSSL",
            context: 'Validating encryption cipher at construction',
            suggestion: 'Use a valid OpenSSL cipher such as aes-256-gcm',
        );
    }
}
