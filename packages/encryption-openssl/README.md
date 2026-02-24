# Marko Encryption OpenSSL

OpenSSL encryption driver--encrypts and decrypts data using AES-256-GCM with authenticated encryption.

## Overview

The OpenSSL encryptor provides authenticated encryption using AES-256-GCM. Each encryption generates a random IV, produces a GCM authentication tag, and encodes the result as a portable base64 payload. Decryption verifies the authentication tag, detecting any tampering or key mismatch.

Implements `EncryptorInterface` from `marko/encryption`.

## Installation

```bash
composer require marko/encryption-openssl
```

This automatically installs `marko/encryption`. Requires the `ext-openssl` PHP extension.

## Usage

### Configuration

Set the encryption key and cipher in your config:

```php
// config/encryption.php
return [
    'key' => $_ENV['ENCRYPTION_KEY'],
    'cipher' => 'aes-256-gcm',
];
```

Generate a key:

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

The key must be a base64-encoded 32-byte value.

### How It Works

Once configured, inject `EncryptorInterface` as usual--the OpenSSL driver is used automatically:

```php
use Marko\Encryption\Contracts\EncryptorInterface;

class SecureStorage
{
    public function __construct(
        private EncryptorInterface $encryptor,
    ) {}

    public function store(
        string $sensitiveData,
    ): string {
        return $this->encryptor->encrypt($sensitiveData);
    }

    public function retrieve(
        string $encrypted,
    ): string {
        return $this->encryptor->decrypt($encrypted);
    }
}
```

### Error Handling

The encryptor throws specific exceptions for different failure modes:

```php
use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Encryption\Exceptions\EncryptionException;

try {
    $value = $this->encryptor->decrypt($token);
} catch (DecryptionException $e) {
    // Invalid payload, wrong key, or tampered data
}
```

## Customization

Replace the encryptor with a Preference to use a different cipher or add logging:

```php
use Marko\Core\Attributes\Preference;
use Marko\Encryption\OpenSsl\OpenSslEncryptor;

#[Preference(replaces: OpenSslEncryptor::class)]
class LoggingEncryptor extends OpenSslEncryptor
{
    public function encrypt(
        string $value,
    ): string {
        $result = parent::encrypt($value);
        // Log encryption event...
        return $result;
    }
}
```

## API Reference

Implements all methods from `EncryptorInterface`. See `marko/encryption` for the full contract.
