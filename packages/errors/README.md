# Marko Errors

Error handling contracts for the Marko Framework.

## What This Package Provides

This package defines the interfaces and data structures for error handling in Marko applications. It contains no implementation code - just contracts that implementations must follow.

The package provides:

- **ErrorHandlerInterface** - Contract for handling errors and exceptions
- **ErrorReporterInterface** - Contract for reporting errors to external services
- **ErrorReport** - Standardized container for error information
- **Severity** - Enum classifying error severity levels

## The Interface/Implementation Pattern

Marko separates interface packages from implementation packages. This package (`marko/errors`) defines *what* error handling looks like. Implementation packages like `marko/errors-simple` define *how* it actually works.

Why split them? You can swap implementations without changing application code. Your services type-hint against `ErrorHandlerInterface`, and the DI container provides whichever implementation is configured. Development might use a detailed HTML error page while production uses a simple text response - same interface, different implementations.

## Core Components

### ErrorHandlerInterface

The primary contract for processing errors in your application.

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

The `handle()` method is the main entry point - it receives an `ErrorReport` containing everything known about the error. The `handleException()` and `handleError()` methods integrate with PHP's native error handling system. Call `register()` to activate the handler and `unregister()` to restore previous handlers.

### ErrorReporterInterface

Contract for sending errors to external monitoring services like Sentry, Bugsnag, or custom logging endpoints.

```php
interface ErrorReporterInterface
{
    public function report(ErrorReport $report): void;
    public function shouldReport(ErrorReport $report): bool;
}
```

Use this when you need centralized error tracking across distributed systems. The `shouldReport()` method lets implementations filter errors by severity, environment, rate limiting, or other criteria before sending.

### ErrorReport

A readonly data class that captures everything about an error in a standardized format.

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
    public string $context;
    public string $suggestion;
    public ?Throwable $previous;

    public static function fromThrowable(Throwable $throwable, Severity $severity): self;
}
```

Create reports using `ErrorReport::fromThrowable()`. This factory method automatically extracts context and suggestions from `MarkoException` instances, giving your error handlers rich information to work with.

### Severity

An enum classifying error severity with four levels:

```php
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Deprecated = 'deprecated';

    public static function fromErrorLevel(int $level): self;
    public function label(): string;
    public function color(): string;
}
```

The `fromErrorLevel()` method maps PHP's error constants (E_WARNING, E_NOTICE, etc.) to severity levels. The `label()` and `color()` methods provide display-friendly representations.

## Relationship to Core Exceptions

The `marko/core` package provides `MarkoException`, which carries additional context beyond standard PHP exceptions:

- **context** - Explains what was happening when the error occurred
- **suggestion** - Offers guidance on how to fix the problem

When `ErrorReport::fromThrowable()` receives a `MarkoException`, it automatically extracts this information. Standard PHP exceptions still work - they just have empty context and suggestion fields.

This design means core throws exceptions with helpful information, and error handlers present that information appropriately for the environment.

## Available Implementations

**marko/errors-simple** - A straightforward implementation suitable for most applications. Provides text and HTML formatters with environment-aware behavior.

**marko/errors-advanced** (planned) - A more sophisticated implementation with features like error grouping, custom renderers, and advanced filtering.

## Creating Custom Implementations

### Custom Error Handler

To create your own error handler, implement `ErrorHandlerInterface`. Your handler receives `ErrorReport` objects and decides how to present errors - logging, rendering HTML, returning JSON, or whatever your application needs.

Register your implementation with the DI container using a Preference, and Marko will inject it wherever `ErrorHandlerInterface` is type-hinted.

### Custom Error Reporter

To send errors to an external service, implement `ErrorReporterInterface`. The `shouldReport()` method lets you filter which errors get sent - you might skip notices in development or rate-limit duplicate errors.

Connect your reporter to your monitoring service of choice: Sentry, Bugsnag, Rollbar, or a custom endpoint.

## Usage in Application Code

Type-hint against the interfaces, not implementations:

```php
public function __construct(
    private ErrorHandlerInterface $errorHandler,
) {}
```

The DI container provides the configured implementation. Your code remains decoupled from specific error handling strategies, making it easy to swap implementations between environments or as requirements change.
