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
    private readonly string $key;

    private readonly string $cipher;

    private readonly int $ivLength;

    /**
     * @throws EncryptionException
     */
    public function __construct(
        private readonly EncryptionConfig $config,
    ) {
        $key = base64_decode($this->config->key(), true);

        if ($key === false || strlen($key) !== 32) {
            throw new EncryptionException(
                message: 'Invalid encryption key',
                context: 'The ENCRYPTION_KEY must be a base64-encoded 32-byte key',
                suggestion: 'Generate a key with: base64_encode(random_bytes(32))',
            );
        }

        $this->key = $key;

        $cipher = strtolower($this->config->cipher());

        if (!in_array($cipher, openssl_get_cipher_methods(), true)) {
            throw EncryptionException::invalidCipher($cipher);
        }

        if (!str_ends_with($cipher, '-gcm') && !str_ends_with($cipher, '-ccm')) {
            throw EncryptionException::nonAeadCipher($cipher);
        }

        $ivLength = openssl_cipher_iv_length($cipher);

        if ($ivLength === false) {
            throw new EncryptionException(
                message: "Failed to determine IV length for cipher '$cipher'",
                context: 'Initializing OpenSSL encryptor at construction',
                suggestion: 'Ensure the cipher is supported by the installed OpenSSL version',
            );
        }

        $this->cipher = $cipher;
        $this->ivLength = $ivLength;
    }

    /**
     * @throws EncryptionException|RandomException
     */
    public function encrypt(
        string $value,
    ): string {
        $iv = random_bytes($this->ivLength);
        $tag = '';

        $encrypted = openssl_encrypt($value, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

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
     * @throws DecryptionException
     */
    public function decrypt(
        string $encrypted,
    ): string {
        $json = base64_decode($encrypted, true);

        if ($json === false) {
            throw DecryptionException::invalidPayload();
        }

        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            throw DecryptionException::invalidPayload();
        }

        if (!is_string($payload['iv'] ?? null) || !is_string($payload['value'] ?? null) || !is_string(
            $payload['tag'] ?? null,
        )) {
            throw DecryptionException::invalidPayload();
        }

        $iv = base64_decode($payload['iv'], true);
        $value = base64_decode($payload['value'], true);
        $tag = base64_decode($payload['tag'], true);

        if ($iv === false || $value === false || $tag === false) {
            throw DecryptionException::invalidPayload();
        }

        $decrypted = openssl_decrypt($value, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw DecryptionException::invalidKey();
        }

        return $decrypted;
    }
}
