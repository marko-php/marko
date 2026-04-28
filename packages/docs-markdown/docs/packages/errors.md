---
title: marko/errors
description: Interfaces for error handling — defines how errors are captured and structured, not how they're displayed.
---

Interfaces for error handling --- defines how errors are captured and structured, not how they're displayed. This package provides the contracts (`ErrorHandlerInterface`, `ErrorReporterInterface`, `FormatterInterface`) and data structures (`ErrorReport`, `Severity`) that error handlers implement. It standardizes how errors are captured: not just the exception, but the context of what was happening and suggestions for how to fix it.

**This package has no implementation.** Install `marko/errors-simple` (or another driver) for actual error handling.

## Installation

```bash
composer require marko/errors
```

Note: You typically install an implementation package (like `marko/errors-simple`) which requires this automatically.

## Usage

### In Your Modules

When building modules in `app/` or `modules/`, you don't need to interact with error handling directly --- it works automatically. Just throw exceptions:

```php
use Marko\Core\Exceptions\MarkoException;

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
use Marko\Core\Attributes\Preference;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\ErrorReport;

#[Preference(replaces: ErrorHandlerInterface::class)]
class MyErrorHandler implements ErrorHandlerInterface
{
    public function handle(
        ErrorReport $report,
    ): void {
        // Your handling logic
    }

    // ... implement other methods
}
```

### Custom Error Reporter

For external services (Sentry, Bugsnag, etc.):

```php
use Marko\Errors\Contracts\ErrorReporterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;

class SentryReporter implements ErrorReporterInterface
{
    public function shouldReport(
        ErrorReport $report,
    ): bool {
        return $report->severity === Severity::Error;
    }

    public function report(
        ErrorReport $report,
    ): void {
        \Sentry\captureException($report->throwable);
    }
}
```

### Custom Formatter

Format error reports for different output targets (text, HTML, JSON, etc.):

```php
use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;

class JsonFormatter implements FormatterInterface
{
    public function format(
        ErrorReport $report,
    ): string {
        return json_encode([
            'id' => $report->id,
            'message' => $report->message,
            'severity' => $report->severity->label(),
            'file' => $report->file,
            'line' => $report->line,
        ]);
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

### FormatterInterface

```php
interface FormatterInterface
{
    public function format(ErrorReport $report): string;
}
```

### ErrorReport

```php
readonly class ErrorReport
{
    public string $id;
    public string $message;
    public int|string $code;
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

## Related Packages

- **marko/errors-simple** --- Zero-dependency fallback handler
- **marko/errors-advanced** --- Rich error pages (planned)
- [marko/core](/docs/packages/core/) --- Provides `MarkoException` with `context` and `suggestion` fields
