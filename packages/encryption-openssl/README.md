# marko/encryption-openssl

OpenSSL encryption driver --- encrypts and decrypts data using AES-256-GCM with authenticated encryption.

## Installation

```bash
composer require marko/encryption-openssl
```

Requires the `ext-openssl` PHP extension. Automatically installs `marko/encryption`.

## Quick Example

```php
use Marko\Encryption\Contracts\EncryptorInterface;

class SecureStorage
{
    public function __construct(
        private EncryptorInterface $encryptor,
    ) {}

    public function store(string $sensitiveData): string
    {
        return $this->encryptor->encrypt($sensitiveData);
    }

    public function retrieve(string $encrypted): string
    {
        return $this->encryptor->decrypt($encrypted);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/encryption-openssl](https://marko.build/docs/packages/encryption-openssl/)
