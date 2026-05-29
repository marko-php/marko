---
title: marko/errors-simple
description: The default error handler --- catches exceptions and displays them with full context and fix suggestions.
---

The default error handler --- catches exceptions and displays them with full context and fix suggestions. This is the implementation of `ErrorHandlerInterface` from [marko/errors](/docs/packages/errors/) that actually catches and displays errors. When something breaks, you see the exception message, the code that caused it, and suggestions for fixing it. Zero external dependencies means it works even when other parts of your application fail.

- **CLI** --- Colored output with code snippets around the error
- **Web** --- Clean HTML page with stack trace and context
- **Development** --- Full details including suggestions from `MarkoException`
- **Production** --- Generic message with error ID (no sensitive paths or code)

## Installation

```bash
composer require marko/errors-simple
```

The handler registers automatically via module boot --- no configuration required.

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
use Marko\Errors\Severity;
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
            $this->fallback->handle(
                ErrorReport::fromThrowable($e, Severity::Error),
            );
        }
    }
}
```

## API Reference

### SimpleErrorHandler

```php
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use Marko\ErrorsSimple\Formatters\TextFormatter;

class SimpleErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        Environment $environment,
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
class Environment
{
    public function __construct(?string $sapi = null, ?array $envVars = null);

    public function isCli(): bool;
    public function isWeb(): bool;
    public function isDevelopment(): bool;
    public function isProduction(): bool;
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
```

### CodeSnippetExtractor

```php
class CodeSnippetExtractor
{
    public function extract(string $filePath, int $lineNumber, int $context = 5): array;
    // Returns: ['lines' => [lineNum => code], 'errorLine' => int]
}
```
