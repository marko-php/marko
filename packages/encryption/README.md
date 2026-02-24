# Marko Encryption

Interfaces for encryption--defines how data is encrypted and decrypted, not which cipher is used.

## Overview

Encryption provides the `EncryptorInterface` contract and shared infrastructure for Marko's encryption system. Type-hint against the interface in your modules and let the installed driver handle the cryptographic implementation. Includes configuration for key management and rich exceptions for invalid keys and corrupted payloads.

**This package defines contracts only.** Install a driver for implementation:

- `marko/encryption-openssl` -- OpenSSL with AES-256-GCM (recommended)

## Installation

```bash
composer require marko/encryption
```

Note: You typically install a driver package (like `marko/encryption-openssl`) which requires this automatically.

## Usage

### Type-Hinting the Encryptor

Inject `EncryptorInterface` wherever you need encryption:

```php
use Marko\Encryption\Contracts\EncryptorInterface;

class TokenService
{
    public function __construct(
        private EncryptorInterface $encryptor,
    ) {}

    public function issueToken(
        array $payload,
    ): string {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->encryptor->encrypt($json);
    }

    public function verifyToken(
        string $token,
    ): array {
        $json = $this->encryptor->decrypt($token);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
```

### Configuration

Set the encryption key and cipher in your config:

```php
// config/encryption.php
return [
    'key' => $_ENV['ENCRYPTION_KEY'],
    'cipher' => 'aes-256-gcm',
];
```

Generate a key with: `base64_encode(random_bytes(32))`

## API Reference

### EncryptorInterface

```php
public function encrypt(string $value): string;
public function decrypt(string $encrypted): string;
```

### Exceptions

- `EncryptionException` -- Encryption operation failed
- `DecryptionException` -- Decryption failed (invalid payload, wrong key, or tampered data)
