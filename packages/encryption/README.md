# marko/encryption

Interfaces for encryption--defines how data is encrypted and decrypted, not which cipher is used.

## Installation

```bash
composer require marko/encryption
```

Note: You typically install a driver package (like `marko/encryption-openssl`) which requires this automatically.

## Quick Example

```php
use Marko\Encryption\Contracts\EncryptorInterface;

class TokenService
{
    public function __construct(
        private EncryptorInterface $encryptor,
    ) {}

    public function issueToken(array $payload): string
    {
        return $this->encryptor->encrypt(
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/encryption](https://marko.build/docs/packages/encryption/)
