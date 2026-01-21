# Marko Errors Simple

The reliable fallback error handler—designed to never fail.

## Overview

This is a zero-dependency error handler that works as the safety net for your application. If your fancy error handler with database logging and Slack notifications fails, this catches *that* failure.

- **CLI**: Colored stack traces with code snippets
- **Web**: Basic HTML pages with inline styles
- **Development**: Full details (stack trace, code context, suggestions)
- **Production**: Generic message with error ID only

## Installation

```bash
composer require marko/errors-simple
```

The handler registers automatically via module boot—no configuration required.

## Usage

### For Module Developers

You don't need to do anything special. Throw exceptions normally and they're handled:

```php
// In your app/mymodule/ or modules/mypackage/ code
throw new \RuntimeException('Something went wrong');

// With MarkoException for richer errors
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

Also accepts: `dev`, `local`, `prod`. Falls back to `APP_ENV` if `MARKO_ENV` not set.

**Safe default**: No env var = production mode.

### Manual Handler Access

If you need direct access:

```php
use Marko\Errors\Contracts\ErrorHandlerInterface;

class MyService
{
    public function __construct(
        private ErrorHandlerInterface $handler,
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
$handler = new SimpleErrorHandler(
    new Environment(),
    new MyTextFormatter(),
    new MyHtmlFormatter(),
);
```

### Using as Fallback

When building a custom handler, delegate failures to this one:

```php
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsSimple\SimpleErrorHandler;

#[Preference(replaces: ErrorHandlerInterface::class)]
class FancyErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private SimpleErrorHandler $fallback,
    ) {}

    public function handle(ErrorReport $report): void
    {
        try {
            $this->sendToSlack($report);
            $this->renderPrettyHtml($report);
        } catch (Throwable $e) {
            // Fancy failed—use the reliable fallback
            $this->fallback->handle(ErrorReport::fromThrowable($e, Severity::Error));
        }
    }
}
```

## API Reference

### SimpleErrorHandler

```php
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
    public function format(ErrorReport $report, bool $isDevelopment): string;
}

class BasicHtmlFormatter
{
    public const CONTENT_TYPE = 'text/html; charset=UTF-8';
    public function format(ErrorReport $report, bool $isDevelopment): string;
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
