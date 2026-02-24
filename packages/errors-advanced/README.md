# Marko Errors Advanced

Pretty error pages with syntax-highlighted code, stack traces, and request details--so you can diagnose issues at a glance during development.

## Overview

Errors Advanced implements `ErrorHandlerInterface` with a rich HTML error page. In development mode, it displays the error message, syntax-highlighted source code around the error line, full stack trace with code context, request data (headers, query, POST), and environment info. In production, it shows a safe generic error page. Sensitive data (passwords, tokens, API keys) is automatically masked in request output. CLI errors fall back to plain text.

## Installation

```bash
composer require marko/errors-advanced
```

This replaces the default `marko/errors-simple` handler with a more detailed error display.

## Usage

### For Module Developers

You do not need to do anything special--just throw exceptions. The advanced error handler is registered automatically and catches all uncaught exceptions:

```php
use Marko\Core\Exceptions\MarkoException;

throw new MarkoException(
    message: 'Order processing failed',
    context: 'Processing order #12345',
    suggestion: 'Check the payment gateway configuration in config/payments.php',
);
```

The error page will display:

- The exception message
- The file and line number
- Syntax-highlighted code around the error
- Full stack trace with expandable code snippets
- Request headers, query params, and POST data
- PHP version and server info

### Environment-Aware Display

- **Development**: Full error details with source code and stack traces
- **Production**: Generic "An error occurred" message with no sensitive details
- **CLI**: Plain text output via the text formatter

### Sensitive Data Masking

Request data displayed in error pages is automatically masked for fields matching:

- `password`, `api_key`, `token`, `secret`, `session`
- `Authorization` header

These appear as `********` in the error output.

## Customization

Replace the formatter via Preferences to customize the error page appearance:

```php
use Marko\Core\Attributes\Preference;
use Marko\ErrorsAdvanced\PrettyHtmlFormatter;

#[Preference(replaces: PrettyHtmlFormatter::class)]
class CustomHtmlFormatter extends PrettyHtmlFormatter
{
    public function format(
        ErrorReport $report,
    ): string {
        // Custom formatting
        return parent::format($report);
    }
}
```

## API Reference

### AdvancedErrorHandler

```php
class AdvancedErrorHandler implements ErrorHandlerInterface
{
    public function handle(ErrorReport $report): void;
    public function handleException(Throwable $exception): void;
    public function handleError(int $level, string $message, string $file, int $line): bool;
    public function register(): void;
    public function unregister(): void;
}
```

### PrettyHtmlFormatter

```php
class PrettyHtmlFormatter implements FormatterInterface
{
    public function format(ErrorReport $report): string;
}
```

### SyntaxHighlighter

```php
class SyntaxHighlighter
{
    public function getCss(): string;
    public function highlight(string $code): string;
    public function highlightWithContext(string $code, int $errorLine, int $contextLines = 3): string;
}
```
