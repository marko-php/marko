<?php

declare(strict_types=1);

namespace Marko\Cache\Signer;

use Marko\Cache\Exceptions\TamperedCacheValueException;
use Marko\Encryption\Config\EncryptionConfig;

/**
 * Signs serialized cache payloads with HMAC-SHA256 so a driver never hands
 * unserialize() bytes it did not write itself.
 *
 * The envelope format is: {64-char-hex-hmac}.{serialized-payload}
 *
 * The MAC covers a context string as well as the payload. Drivers pass the
 * storage key the entry lives under, so a validly signed value moved to another
 * key no longer verifies. The HMAC key is a subkey derived from the app
 * encryption key with HKDF, so the key that encrypts data never also signs
 * cache entries.
 *
 * Every MAC goes through mac(), and the key comes from signingKey(), so the key
 * derivation and the MAC inputs can change in one place.
 */
readonly class CacheValueSigner
{
    /**
     * HKDF info string that scopes the derived subkey to cache entries.
     */
    public const string KEY_PURPOSE = 'marko-cache-signer';

    private const int MAC_LENGTH = 64;

    private const string SEPARATOR = '.';

    public function __construct(
        private EncryptionConfig $encryptionConfig,
    ) {}

    /**
     * Wrap a serialized value in an HMAC-signed envelope bound to $context.
     *
     * @param string $context The storage key the entry is written under
     *
     * @throws TamperedCacheValueException when the signing key is empty
     */
    public function wrap(
        string $serialized,
        string $context,
    ): string {
        return $this->mac($serialized, $context) . self::SEPARATOR . $serialized;
    }

    /**
     * Verify an HMAC-signed envelope and return the inner serialized bytes, or
     * null when the envelope is malformed, unsigned, signed for another context,
     * or its HMAC does not match.
     *
     * A missing signing key is a configuration error rather than a bad entry,
     * so it still throws.
     *
     * @param string $context The storage key the entry was read from
     *
     * @throws TamperedCacheValueException when the signing key is empty
     */
    public function unwrap(
        string $envelope,
        string $context,
    ): ?string {
        $hmac = substr($envelope, 0, self::MAC_LENGTH);
        $separator = substr($envelope, self::MAC_LENGTH, 1);
        $serialized = substr($envelope, self::MAC_LENGTH + 1);

        // The MAC is computed before the separator check so an empty key is
        // reported even when the envelope is malformed or unsigned.
        $expectedHmac = $this->mac($serialized, $context);

        if (
            $separator !== self::SEPARATOR
            || !hash_equals($expectedHmac, $hmac)
        ) {
            return null;
        }

        return $serialized;
    }

    /**
     * Verify an HMAC-signed envelope and return the inner serialized bytes.
     *
     * @param string $context The storage key the entry was read from
     *
     * @throws TamperedCacheValueException when the envelope HMAC does not verify or the key is empty
     */
    public function verifyAndUnwrap(
        string $envelope,
        string $context,
    ): string {
        return $this->unwrap($envelope, $context) ?? throw TamperedCacheValueException::signatureMismatch();
    }

    /**
     * MAC over the length-prefixed context followed by the payload. The length
     * prefix keeps the boundary unambiguous, so no two context/payload pairs
     * produce the same MAC input.
     *
     * @throws TamperedCacheValueException when the signing key is empty
     */
    private function mac(
        string $payload,
        string $context,
    ): string {
        return hash_hmac('sha256', strlen($context) . ':' . $context . $payload, $this->signingKey());
    }

    /**
     * Derive the cache-signer HMAC subkey from the app encryption key.
     *
     * @throws TamperedCacheValueException when the signing key is empty
     */
    private function signingKey(): string
    {
        $key = $this->encryptionConfig->key();

        if ($key === '') {
            throw TamperedCacheValueException::emptySigningKey();
        }

        return hash_hkdf('sha256', $key, 32, self::KEY_PURPOSE);
    }
}
