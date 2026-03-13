# marko/errors

Interfaces for error handling — defines how errors are captured and structured, not how they're displayed.

## Installation

```bash
composer require marko/errors
```

Note: You typically install an implementation package (like `marko/errors-simple`) which requires this automatically.

## Quick Example

```php
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;

$report = ErrorReport::fromThrowable($exception, Severity::Error);
```

## Documentation

Full usage, API reference, and examples: [marko/errors](https://marko.build/docs/packages/errors/)
