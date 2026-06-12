<?php

declare(strict_types=1);

namespace Marko\Cache\Redis\Exceptions;

use Marko\Cache\Exceptions\CacheException;

class TamperedCacheValueException extends CacheException
{
    public static function signatureMismatch(): self
    {
        return new self(
            message: 'Cache value HMAC signature does not match — possible tampering or data corruption.',
            context: 'Verifying HMAC-SHA256 signature of cached value envelope.',
            suggestion: 'Do not modify cached values directly. Ensure all writers use the same app key.',
        );
    }

    public static function emptySigningKey(): self
    {
        return new self(
            message: 'Cannot sign or verify cached value: the encryption key is empty.',
            context: 'Reading encryption.key from config for HMAC signing.',
            suggestion: 'Set the ENCRYPTION_KEY environment variable before starting the application.',
        );
    }
}
