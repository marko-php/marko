<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Fixtures;

use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\Exceptions\DecryptionException;

/**
 * Reversible stand-in for a real encryptor: the output never contains the plain text,
 * so tests can assert that a secret does not reach serialized jobs. Like a real AEAD
 * encryptor, decryption fails unless the same associated data is given.
 */
class FakeEncryptor implements EncryptorInterface
{
    private const string PREFIX = 'fake-encrypted:';

    public function encrypt(
        string $value,
        string $aad = '',
    ): string {
        return self::PREFIX . base64_encode($aad) . ':' . base64_encode(strrev($value));
    }

    public function decrypt(
        string $encrypted,
        string $aad = '',
    ): string {
        $parts = explode(':', substr($encrypted, strlen(self::PREFIX)), 2);

        if (
            !str_starts_with($encrypted, self::PREFIX)
            || count($parts) !== 2
            || $parts[0] !== base64_encode($aad)
        ) {
            throw DecryptionException::invalidPayload();
        }

        return strrev((string) base64_decode($parts[1], true));
    }
}
