<?php

declare(strict_types=1);

namespace Marko\Encryption\OpenSsl;

use JsonException;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Encryption\Exceptions\EncryptionException;

class OpenSslEncryptor implements EncryptorInterface
{
    private readonly string $key;

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
    }

    /**
     * @throws EncryptionException
     */
    public function encrypt(
        string $value,
    ): string {
        $cipher = $this->config->cipher();
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = random_bytes($ivLength);
        $tag = '';

        $encrypted = openssl_encrypt($value, $cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

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

        if (!is_array($payload) || !isset($payload['iv'], $payload['value'], $payload['tag'])) {
            throw DecryptionException::invalidPayload();
        }

        $iv = base64_decode($payload['iv'], true);
        $value = base64_decode($payload['value'], true);
        $tag = base64_decode($payload['tag'], true);

        if ($iv === false || $value === false || $tag === false) {
            throw DecryptionException::invalidPayload();
        }

        $decrypted = openssl_decrypt($value, $this->config->cipher(), $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw DecryptionException::invalidKey();
        }

        return $decrypted;
    }
}
