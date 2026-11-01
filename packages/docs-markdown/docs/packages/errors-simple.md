---
title: marko/errors-simple
description: The default error handler --- catches exceptions and displays them with full context and fix suggestions.
---

The default error handler --- catches exceptions and displays them with full context and fix suggestions. This is the implementation of `ErrorHandlerInterface` from [marko/errors](/docs/packages/errors/) that actually catches and displays errors. When something breaks, you see the exception message, the code that caused it, and suggestions for fixing it. Zero external dependencies means it works even when other parts of your application fail.

- **CLI** --- Colored output with code snippets around the error
- **Web** --- Clean HTML page with stack trace and context
- **Development** --- Full details including suggestions from `MarkoException`
- **Production** --- Generic message with error ID (no sensitive paths or code)

In the web SAPI the handler sets the HTTP status (`500`, or the status of an `HttpExceptionInterface` that reached it) and renders JSON instead of HTML when the client asks for it (see [Status Codes and JSON](#status-codes-and-json)).

Non-fatal PHP errors (warnings, notices, deprecations) are reported via `error_log` in the web SAPI rather than halting the response or replacing the page with a 500 error. Fatal errors and uncaught exceptions still replace the response.

## Installation

```bash
composer require marko/errors-simple
```

The handler registers automatically via module boot --- no configuration required. `ErrorHandlerInterface` is bound as a singleton, and the module's `shutdown` callback unregisters the handler when [`Application::shutdown()`](/docs/packages/core/#shutting-down) runs (marko/testing does this after every test that booted the application).

## Usage

### For Module Developers

You don't need to do anything special. Throw exceptions normally and they're handled:

```php
// In your app/mymodule/ or modules/mypackage/ code
throw new \RuntimeException('Something went wrong');
```

Use `MarkoException` for richer errors with context and fix suggestions:

```php
use Marko\Core\Exceptions\MarkoException;

throw new MarkoException(
    message: 'Configuration invalid',
    context: 'Loading payment gateway settings',
    suggestion: 'Check that API_KEY is set in your .env file',
);
```

### Setting Environment Mode

Control detail level via environment variable:

```bash
# Development - full error details
MARKO_ENV=development

# Production - generic messages (default)
MARKO_ENV=production
```

Also accepts: `dev`, `local`. Falls back to `APP_ENV` if `MARKO_ENV` is not set. Any other value (or no value) is treated as production.

**Safe default:** No env var = production mode.

Detection is delegated to core's [`AppEnvironment`](/docs/packages/core/#application-environment), so error display agrees with the rest of the framework on which names mean development. Values are read from `$_ENV` with a `getenv()` fallback.

### Status Codes and JSON

Uncaught exceptions are sent with status `500`. Exceptions that carry an HTTP meaning --- anything implementing `Marko\Core\Exceptions\HttpExceptionInterface`, such as `HttpException::notFound()` or `ValidationException` --- are normally rendered inside the routing pipeline (see [Errors and HTTP Exceptions](/docs/packages/routing/#errors-and-http-exceptions)) and never reach the handler. If one is thrown outside the pipeline (for example in a boot callback during a request), the handler uses its status and headers instead of `500`.

When the request `Accept` header contains `application/json` or a `+json` type (or there is no `Accept` header and the `Content-Type` is JSON), the handler responds with `Content-Type: application/json`:

```json
// Production
{"message": "Server Error"}

// Development
{
    "message": "Undefined variable $post",
    "exception": "ErrorException",
    "file": "/app/blog/src/Controller/PostController.php",
    "line": 42,
    "trace": ["/app/... App\\Blog\\Controller\\PostController->show()", "..."]
}
```

The development trace is trimmed to the first 20 frames. In production the body never contains the exception message, file, or trace; an `HttpExceptionInterface` contributes only its client-safe `getResponseData()`.

### Manual Handler Access

If you need direct access to the handler, type-hint the interface:

```php
use Marko\Errors\Contracts\ErrorHandlerInterface;

class MyService
{
    public function __construct(
        private ErrorHandlerInterface $errorHandler,
    ) {}
}
```

## Customization

### Custom Formatters

Extend the built-in formatters:

```php
use Marko\ErrorsSimple\Formatters\TextFormatter;

class MyTextFormatter extends TextFormatter
{
    // Override methods as needed
}
```

Inject via constructor:

```php
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\SimpleErrorHandler;

$handler = new SimpleErrorHandler(
    new Environment(),
    $clock, // Psr\Clock\ClockInterface
    new MyTextFormatter(),
    new MyHtmlFormatter(),
);
```

### Using as Fallback

When building a custom handler, delegate failures to this one:

```php
use Marko\Core\Attributes\Preference;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\ErrorReport;
use Marko\ErrorsSimple\SimpleErrorHandler;
use Throwable;

#[Preference(replaces: ErrorHandlerInterface::class)]
class FancyErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private SimpleErrorHandler $fallback,
    ) {}

    public function handle(
        ErrorReport $report,
    ): void {
        try {
            $this->sendToSlack($report);
            $this->renderPrettyHtml($report);
        } catch (Throwable $e) {
            // Fancy failed --- use the reliable fallback
            $this->fallback->handleException($e);
        }
    }
}
```

## API Reference

### SimpleErrorHandler

The handler stamps each `ErrorReport` it builds with the time from the injected PSR-20 `ClockInterface` ([`marko/clock`](/docs/packages/clock/)), so a [`FakeClock`](/docs/packages/testing/#fakeclock) freezes report timestamps in tests.

```php
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Psr\Clock\ClockInterface;

class SimpleErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        Environment $environment,
        ClockInterface $clock,
        ?TextFormatter $textFormatter = null,
        ?BasicHtmlFormatter $htmlFormatter = null,
    );

    public function handle(ErrorReport $report): void;
    public function handleException(Throwable $exception): void;
    public function handleError(int $level, string $message, string $file, int $line): bool;
    public function register(): void;
    public function unregister(): void;
}
```

### Environment

```php
use Marko\Core\Environment\AppEnvironment;

class Environment
{
    public function __construct(
        ?string $sapi = null,
        ?array $envVars = null,
        ?array $server = null,
        ?AppEnvironment $appEnvironment = null, // defaults to an AppEnvironment built from $envVars
    );

    public function isCli(): bool;
    public function isWeb(): bool;
    public function isDevelopment(): bool; // delegates to AppEnvironment::isDevelopment()
    public function isProduction(): bool; // !isDevelopment(): unset, staging, etc. hide details
    public function appEnvironment(): AppEnvironment;
    public function acceptsJson(): bool; // reads $server, defaulting to $_SERVER
}
```

### Formatters

```php
class TextFormatter
{
    public function __construct(
        Environment $environment,
        CodeSnippetExtractor $codeSnippetExtractor,
        ?bool $colorsEnabled = null,
    );

    public function format(ErrorReport $report): string;
}

class BasicHtmlFormatter
{
    public const CONTENT_TYPE = 'text/html; charset=UTF-8';

    public function __construct(
        Environment $environment,
        CodeSnippetExtractor $extractor,
    );

    public function format(ErrorReport $report): string;
}

class JsonFormatter implements FormatterInterface
{
    public const CONTENT_TYPE = 'application/json';
    public const MAX_TRACE_FRAMES = 20;

    public function __construct(Environment $environment);

    public function format(ErrorReport $report): string;
}
```

`Marko\ErrorsSimple\HttpErrorStatus::statusCode(Throwable)` and `::headers(Throwable)` return the status (`500` unless the throwable implements `HttpExceptionInterface`) and headers both handlers send.

### CodeSnippetExtractor

```php
class CodeSnippetExtractor
{
    public function extract(string $filePath, int $lineNumber, int $context = 5): array;
    // Returns: ['lines' => [lineNum => code], 'errorLine' => int]
}
```
