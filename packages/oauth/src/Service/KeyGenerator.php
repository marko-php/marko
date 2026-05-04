<?php

declare(strict_types=1);

namespace Marko\OAuth\Service;

use Marko\OAuth\Exceptions\OAuthException;
use OpenSSLAsymmetricKey;

readonly class KeyGenerator
{
    /**
     * @throws OAuthException
     */
    public function generate(
        string $privateKeyPath,
        string $publicKeyPath,
        ?string $passphrase = null,
        bool $force = false,
    ): void {
        if (!$force && file_exists($privateKeyPath)) {
            throw OAuthException::keyFileExists($privateKeyPath);
        }

        if (!$force && file_exists($publicKeyPath)) {
            throw OAuthException::keyFileExists($publicKeyPath);
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (!$key instanceof OpenSSLAsymmetricKey) {
            throw OAuthException::keyGenerationFailed();
        }

        $privateKey = '';
        if (!openssl_pkey_export($key, $privateKey, $passphrase)) {
            throw OAuthException::keyGenerationFailed();
        }

        $details = openssl_pkey_get_details($key);
        $publicKey = is_array($details) ? ($details['key'] ?? null) : null;

        if (!is_string($publicKey) || $publicKey === '') {
            throw OAuthException::keyGenerationFailed();
        }

        $this->ensureDirectory($privateKeyPath);
        $this->ensureDirectory($publicKeyPath);

        if (file_put_contents($privateKeyPath, $privateKey) === false) {
            throw OAuthException::keyWriteFailed($privateKeyPath);
        }

        if (file_put_contents($publicKeyPath, $publicKey) === false) {
            throw OAuthException::keyWriteFailed($publicKeyPath);
        }

        @chmod($privateKeyPath, 0600);
        @chmod($publicKeyPath, 0644);
    }

    private function ensureDirectory(
        string $path,
    ): void {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw OAuthException::keyDirectoryFailed($directory);
            }
        }
    }
}
