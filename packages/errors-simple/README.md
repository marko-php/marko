# marko/errors-simple

The default error handler --- catches exceptions and displays them with full context and fix suggestions.

## Installation

```bash
composer require marko/errors-simple
```

## Quick Example

```php
use Marko\Core\Exceptions\MarkoException;

// Throw rich exceptions --- the handler displays context and fix suggestions automatically
throw new MarkoException(
    message: 'Configuration invalid',
    context: 'Loading payment gateway settings',
    suggestion: 'Check that API_KEY is set in your .env file',
);
```

## Documentation

Full usage, API reference, and examples: [marko/errors-simple](https://marko.build/docs/packages/errors-simple/)
