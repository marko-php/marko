# marko/hashing

Password hashing and verification with configurable algorithms--hash passwords securely without worrying about algorithm details.

## Installation

```bash
composer require marko/hashing
```

## Quick Example

```php
use Marko\Hashing\HashManager;

$hash = $hashManager->hash('secret');
$hashManager->verify('secret', $hash); // true
$hashManager->needsRehash($hash);      // false (until config changes)
```

## Documentation

Full usage, API reference, and examples: [marko/hashing](https://marko.build/docs/packages/hashing/)
