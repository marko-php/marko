<?php

declare(strict_types=1);

namespace Marko\Encryption\OpenSsl;

use JsonException;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Encryption\Exceptions\EncryptionException;
use Random\RandomException;

class OpenSslEncryptor implements EncryptorInterface
{
    /**
     * Full-length AEAD authentication tag. Decryption rejects anything shorter,
     * because OpenSSL only verifies as many tag bytes as it is given.
     */
    private const int TAG_LENGTH = 16;

    private readonly string $key;

    /**
     * Retired keys, tried for decryption only, in configured order.
     *
     * @var list<string>
     */
    private readonly array $previousKeys;

    private readonly bool $aadFallback;

    private readonly string $cipher;

    private readonly int $ivLength;

    /**
     * @throws EncryptionException
     */
    public function __construct(
        private readonly EncryptionConfig $config,
    ) {
        $cipher = strtolower($this->config->cipher());

        if (!in_array($cipher, openssl_get_cipher_methods(), true)) {
            throw EncryptionException::invalidCipher($cipher);
        }

        if (
            !str_ends_with($cipher, '-gcm')
            && !str_ends_with($cipher, '-ccm')
        ) {
            throw EncryptionException::nonAeadCipher($cipher);
        }

        $ivLength = openssl_cipher_iv_length($cipher);
        $keyLength = openssl_cipher_key_length($cipher);

        if (
            $ivLength === false
            || $keyLength === false
        ) {
            throw new EncryptionException(
                message: "Failed to determine IV or key length for cipher '$cipher'",
                context: 'Initializing OpenSSL encryptor at construction',
                suggestion: 'Ensure the cipher is supported by the installed OpenSSL version',
            );
        }

        $key = $this->decodeKey($this->config->key(), $keyLength);

        if ($key === null) {
            throw EncryptionException::invalidKeyLength($cipher, $keyLength);
        }

        $previousKeys = [];

        foreach ($this->config->previousKeys() as $index => $previousKey) {
            $decoded = $this->decodeKey($previousKey, $keyLength);

            if ($decoded === null) {
                throw EncryptionException::invalidPreviousKey($index, $cipher, $keyLength);
            }

            $previousKeys[] = $decoded;
        }

        $this->key = $key;
        $this->previousKeys = $previousKeys;
        $this->aadFallback = $this->config->aadFallback();
        $this->cipher = $cipher;
        $this->ivLength = $ivLength;
    }

    /**
     * @throws EncryptionException|RandomException
     */
    public function encrypt(
        string $value,
        string $aad = '',
    ): string {
        $iv = random_bytes($this->ivLength);
        $tag = '';

        $encrypted = openssl_encrypt(
            $value,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            self::TAG_LENGTH,
        );

        if ($encrypted === false) {
            throw new EncryptionException(
                message: 'Encryption failed',
                context: 'OpenSSL encryption operation returned false',
                suggestion: 'Verify the encryption cipher and key are valid',
            );
        }

        try {
            $payload = json_encode([
                'iv' => base64_encode($iv),
                'value' => base64_encode($encrypted),
                'tag' => base64_encode($tag),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new EncryptionException(
                message: 'Failed to encode encrypted payload',
                context: 'JSON encoding of encryption payload failed',
                suggestion: 'This is an unexpected internal error',
                previous: $e,
            );
        }

        return base64_encode($payload);
    }

    /**
     * Decrypt with the current key, then each previous key. When that fails for a
     * non-empty $aad and encryption.aad_fallback is on, every key is retried with
     * empty associated data so values written before AAD was introduced still decrypt.
     *
     * @throws DecryptionException
     */
    public function decrypt(
        string $encrypted,
        string $aad = '',
    ): string {
        $json = base64_decode($encrypted, true);

        if ($json === false) {
            throw DecryptionException::invalidPayload();
        }

        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            throw DecryptionException::invalidPayload();
        }

        if (
            !is_string($payload['iv'] ?? null)
            || !is_string($payload['value'] ?? null)
            || !is_string(
                $payload['tag'] ?? null,
            )
        ) {
            throw DecryptionException::invalidPayload();
        }

        $iv = base64_decode($payload['iv'], true);
        $value = base64_decode($payload['value'], true);
        $tag = base64_decode($payload['tag'], true);

        if (
            $iv === false
            || $value === false
            || $tag === false
        ) {
            throw DecryptionException::invalidPayload();
        }

        if (strlen($iv) !== $this->ivLength) {
            throw DecryptionException::invalidIvLength(strlen($iv), $this->ivLength);
        }

        if (strlen($tag) !== self::TAG_LENGTH) {
            throw DecryptionException::invalidTagLength(strlen($tag), self::TAG_LENGTH);
        }

        $aadCandidates = ($aad !== '' && $this->aadFallback)
            ? [$aad, '']
            : [$aad];

        foreach ($aadCandidates as $candidateAad) {
            foreach ([$this->key, ...$this->previousKeys] as $key) {
                $decrypted = openssl_decrypt($value, $this->cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, $candidateAad);

                if ($decrypted !== false) {
                    return $decrypted;
                }
            }
        }

        throw DecryptionException::invalidKey();
    }

    /**
     * Decode a base64 key, or null when it is not valid base64 or not exactly $length bytes.
     */
    private function decodeKey(
        string $encoded,
        int $length,
    ): ?string {
        $key = base64_decode($encoded, true);

        return ($key !== false && strlen($key) === $length)
            ? $key
            : null;
    }
}
