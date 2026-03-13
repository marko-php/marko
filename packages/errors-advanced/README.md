# marko/errors-advanced

Pretty error pages with syntax-highlighted code, stack traces, and request details --- so you can diagnose issues at a glance during development.

## Installation

```bash
composer require marko/errors-advanced
```

## Quick Example

Just throw exceptions --- the advanced error handler catches them automatically and renders a rich HTML error page:

```php
use Marko\Core\Exceptions\MarkoException;

throw new MarkoException(
    message: 'Order processing failed',
    context: 'Processing order #12345',
    suggestion: 'Check the payment gateway configuration in config/payments.php',
);
```

## Documentation

Full usage, API reference, and examples: [marko/errors-advanced](https://marko.build/docs/packages/errors-advanced/)
