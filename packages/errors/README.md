# Marko Errors

Error handling contracts that capture not just what went wrong, but the context and how to fix it.

## Overview

When errors happen, you need more than a stack trace. This package provides a standardized way to capture the full picture: what the code was doing, why it failed, and what to do about it. `MarkoException` errors automatically include context and suggestions that flow through to your error output.

This package defines interfaces only—implementations like `marko/errors-simple` handle the actual display.

## Installation

```bash
composer require marko/errors
```

Note: You typically install an implementation package (like `marko/errors-simple`) which requires this automatically.

## Usage

### In Your Modules

When building modules in `app/` or `modules/`, you don't need to interact with error handling directly—it works automatically. Just throw exceptions:

```php
// Your module code - just throw exceptions normally
throw new \RuntimeException('Something went wrong');

// Or use MarkoException for richer context
throw new MarkoException(
    message: 'User not found',
    context: 'Loading user profile for dashboard',
    suggestion: 'Verify the user ID exists in the database',
);
```

The registered error handler catches and formats these automatically.

### Type-Hinting the Handler

If you need to interact with the error handler directly:

```php
use Marko\Errors\Contracts\ErrorHandlerInterface;

class MyService
{
    public function __construct(
        private ErrorHandlerInterface $errorHandler,
    ) {}

    public function doSomething(): void
    {
        // Manually handle an exception if needed
        try {
            $this->riskyOperation();
        } catch (Throwable $e) {
            $this->errorHandler->handleException($e);
        }
    }
}
```

### Creating Error Reports

```php
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;

$report = ErrorReport::fromThrowable($exception, Severity::Error);
```

## Creating Custom Implementations

### Custom Error Handler

Implement `ErrorHandlerInterface` and register via Preference:

```php
use Marko\Errors\Contracts\ErrorHandlerInterface;

#[Preference(replaces: ErrorHandlerInterface::class)]
class MyErrorHandler implements ErrorHandlerInterface
{
    public function handle(ErrorReport $report): void
    {
        // Your handling logic
    }

    // ... implement other methods
}
```

### Custom Error Reporter

For external services (Sentry, Bugsnag, etc.):

```php
use Marko\Errors\Contracts\ErrorReporterInterface;

class SentryReporter implements ErrorReporterInterface
{
    public function shouldReport(ErrorReport $report): bool
    {
        return $report->severity === Severity::Error;
    }

    public function report(ErrorReport $report): void
    {
        \Sentry\captureException($report->throwable);
    }
}
```

## API Reference

### ErrorHandlerInterface

```php
interface ErrorHandlerInterface
{
    public function handle(ErrorReport $report): void;
    public function handleException(Throwable $exception): void;
    public function handleError(int $level, string $message, string $file, int $line): bool;
    public function register(): void;
    public function unregister(): void;
}
```

### ErrorReporterInterface

```php
interface ErrorReporterInterface
{
    public function report(ErrorReport $report): void;
    public function shouldReport(ErrorReport $report): bool;
}
```

### ErrorReport

```php
readonly class ErrorReport
{
    public string $id;
    public string $message;
    public int $code;
    public Throwable $throwable;
    public array $trace;
    public string $file;
    public int $line;
    public Severity $severity;
    public DateTimeImmutable $timestamp;
    public string $context;      // From MarkoException
    public string $suggestion;   // From MarkoException
    public ?Throwable $previous;

    public static function fromThrowable(Throwable $throwable, Severity $severity): self;
}
```

### Severity

```php
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Deprecated = 'deprecated';

    public static function fromErrorLevel(int $level): self;
    public function label(): string;
    public function color(): string;  // ANSI color code
}
```

## Available Implementations

- **marko/errors-simple** — Zero-dependency fallback handler
- **marko/errors-advanced** — Rich error pages (planned)
