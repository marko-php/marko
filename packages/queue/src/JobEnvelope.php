<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Exceptions\SerializationException;

readonly class JobEnvelope
{
    public function __construct(
        private EncryptionConfig $encryptionConfig,
    ) {}

    /**
     * Wrap a serialized job payload in an HMAC-signed envelope.
     *
     * The envelope format is: {64-char-hex-hmac}.{serialized-payload}
     *
     * @throws SerializationException when the signing key is empty
     */
    public function wrap(
        string $serialized,
    ): string {
        $key = $this->encryptionConfig->key();

        if ($key === '') {
            throw SerializationException::emptySigningKey();
        }

        $hmac = hash_hmac('sha256', $serialized, $key);

        return $hmac . '.' . $serialized;
    }

    /**
     * Verify an HMAC-signed envelope and return the inner serialized bytes.
     *
     * @throws SerializationException when the envelope HMAC does not verify or the key is empty
     */
    public function verifyAndUnwrap(
        string $envelope,
    ): string {
        $key = $this->encryptionConfig->key();

        if ($key === '') {
            throw SerializationException::emptySigningKey();
        }

        $hmac = substr($envelope, 0, 64);
        $separator = substr($envelope, 64, 1);
        $serialized = substr($envelope, 65);

        if ($separator !== '.') {
            throw SerializationException::signatureMismatch();
        }

        $expectedHmac = hash_hmac('sha256', $serialized, $key);

        if (!hash_equals($expectedHmac, $hmac)) {
            throw SerializationException::signatureMismatch();
        }

        return $serialized;
    }
}
