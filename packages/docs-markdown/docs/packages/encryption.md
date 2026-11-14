---
title: marko/encryption
description: Interfaces for encryption — defines how data is encrypted and decrypted, not which cipher is used.
---

Interfaces for encryption --- defines how data is encrypted and decrypted, not which cipher is used. Encryption provides the `EncryptorInterface` contract and shared infrastructure for Marko's encryption system. Type-hint against the interface in your modules and let the installed driver handle the cryptographic implementation. Includes configuration for key management and rich exceptions for invalid keys and corrupted payloads.

**This package defines contracts only.** Install a driver for implementation:

- [`marko/encryption-openssl`](/docs/packages/encryption-openssl/) --- OpenSSL with AES-256-GCM (recommended)

## Installation

```bash
composer require marko/encryption
```

Note: You typically install a driver package (like `marko/encryption-openssl`) which requires this automatically.

## Usage

### Type-Hinting the Encryptor

Inject `EncryptorInterface` wherever you need encryption:

```php
use JsonException;
use Marko\Encryption\Contracts\EncryptorInterface;

readonly class TokenService
{
    public function __construct(
        private EncryptorInterface $encryptor,
    ) {}

    /**
     * @throws JsonException
     */
    public function issueToken(
        array $payload,
    ): string {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->encryptor->encrypt($json);
    }

    /**
     * @throws JsonException
     */
    public function verifyToken(
        string $token,
    ): array {
        $json = $this->encryptor->decrypt($token);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
```

### Binding Ciphertext with Associated Data

`encrypt()` and `decrypt()` take an optional `$aad` (associated data) argument. The associated data is authenticated but neither encrypted nor stored in the payload, so decryption only succeeds when the same `$aad` is passed back. Use it to bind a ciphertext to where it lives, so it cannot be copied into another field and still decrypt:

```php
$encrypted = $this->encryptor->encrypt($ssn, 'users.ssn');

$this->encryptor->decrypt($encrypted, 'users.ssn');      // the SSN
$this->encryptor->decrypt($encrypted, 'users.nickname'); // throws DecryptionException
```

Values encrypted before you started passing `$aad` were bound to empty associated data. While `encryption.aad_fallback` is `true` (the default for this release), a failed decryption with a non-empty `$aad` is retried with empty associated data, so those values keep decrypting. Re-encrypt them (read and save each value) and then set `ENCRYPTION_AAD_FALLBACK=false`; until you do, legacy values can still be moved between fields. The default changes to `false` in a future release.

### Rotating the Key

Move the current key into `encryption.previous_keys` and set a new `ENCRYPTION_KEY`. New values are always encrypted with the current key; decryption tries the current key first, then each previous key in order:

```dotenv
ENCRYPTION_KEY=new-base64-key
ENCRYPTION_PREVIOUS_KEYS=old-base64-key,older-base64-key
```

Once every stored value has been re-encrypted with the new key, remove the old key from the list. Each previous key must be valid for the configured cipher, or construction fails with `EncryptionException`.

### Encrypting Entity Columns

To store an entity property encrypted, mark it `#[Encrypted]` instead of encrypting and decrypting by hand in a repository. `marko/database` encrypts on save and decrypts on hydration using the bound `EncryptorInterface`, binding each value to its `table.column` as associated data. See [Encrypted Columns](/docs/packages/database/#encrypted-columns).

### Configuration

The `EncryptionConfig` class provides typed access to encryption configuration values:

```php
use Marko\Encryption\Config\EncryptionConfig;

class MyService
{
    public function __construct(
        private EncryptionConfig $encryptionConfig,
    ) {}

    public function setup(): void
    {
        $key = $this->encryptionConfig->key();
        $cipher = $this->encryptionConfig->cipher();
        $previousKeys = $this->encryptionConfig->previousKeys();
        $aadFallback = $this->encryptionConfig->aadFallback();
    }
}
```

Set the encryption key and cipher in your config:

```php title="config/encryption.php"
use Marko\Config\Env;

return [
    'key' => Env::string('ENCRYPTION_KEY', ''),
    'cipher' => Env::string('ENCRYPTION_CIPHER', 'aes-256-gcm'),
    'previous_keys' => Env::list('ENCRYPTION_PREVIOUS_KEYS', []),
    'aad_fallback' => Env::bool('ENCRYPTION_AAD_FALLBACK', true),
];
```

| Key | Env variable | Default | Description |
|-----|--------------|---------|-------------|
| `key` | `ENCRYPTION_KEY` | `''` | Base64-encoded key. Its length must match the cipher: 32 bytes for `aes-256-*`, 24 for `aes-192-*`, 16 for `aes-128-*` |
| `cipher` | `ENCRYPTION_CIPHER` | `aes-256-gcm` | AEAD cipher (`-gcm` or `-ccm`) |
| `previous_keys` | `ENCRYPTION_PREVIOUS_KEYS` | `[]` | Comma-separated retired keys, used for decryption only (see [Rotating the Key](#rotating-the-key)) |
| `aad_fallback` | `ENCRYPTION_AAD_FALLBACK` | `true` | Retry decryption with empty associated data (see [Binding Ciphertext with Associated Data](#binding-ciphertext-with-associated-data)) |

Generate a key with: `base64_encode(random_bytes(32))` (use `random_bytes(16)` for `aes-128-gcm`)

## API Reference

### EncryptorInterface

```php
use Marko\Encryption\Contracts\EncryptorInterface;

public function encrypt(
    string $value,
    string $aad = '',
): string;
public function decrypt(
    string $encrypted,
    string $aad = '',
): string;
```

`encrypt()` throws `EncryptionException` on failure. `decrypt()` throws `DecryptionException` for invalid payloads, wrong keys, mismatched associated data, or tampered data.

### EncryptionConfig

```php
use Marko\Encryption\Config\EncryptionConfig;

public function key(): string;
public function cipher(): string;
/** @return list<string> */
public function previousKeys(): array;
public function aadFallback(): bool;
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `EncryptionException` | Base exception for all encryption errors --- includes `getContext()` and `getSuggestion()` methods |
| `DecryptionException` | Thrown when decryption fails (invalid payload, wrong key, or tampered data) |

`DecryptionException` extends `EncryptionException` and provides static factory methods:

```php
use Marko\Encryption\Exceptions\DecryptionException;

DecryptionException::invalidPayload(); // corrupted or tampered data
DecryptionException::invalidKey();     // wrong encryption key
DecryptionException::invalidTagLength(actual: 1, expected: 16); // truncated authentication tag
DecryptionException::invalidIvLength(actual: 8, expected: 12);  // IV length does not match the cipher
```

`EncryptionException` factories raised when the encryptor is constructed:

```php
use Marko\Encryption\Exceptions\EncryptionException;

EncryptionException::invalidKeyLength(cipher: 'aes-128-gcm', expectedLength: 16);             // key missing, not base64, or wrong length
EncryptionException::invalidPreviousKey(index: 0, cipher: 'aes-256-gcm', expectedLength: 32); // a previous_keys entry is invalid
EncryptionException::invalidPreviousKeys();                                                    // previous_keys is not a list of strings
EncryptionException::invalidCipher('not-a-cipher');                                            // unknown to OpenSSL
EncryptionException::nonAeadCipher('aes-256-cbc');                                             // not an AEAD mode
```
