---
title: marko/errors-advanced
description: Pretty error pages with syntax-highlighted code, stack traces, and request details for fast debugging during development.
---

Pretty error pages with syntax-highlighted code, stack traces, and request details --- so you can diagnose issues at a glance during development. Errors Advanced implements `ErrorHandlerInterface` with a rich HTML error page that displays the error message, syntax-highlighted source code around the error line, full stack trace with code context, request data (headers, query, POST), and environment info. In production, it shows a safe generic error page and sends status `500`; API clients that accept JSON get a JSON body instead. Sensitive data (passwords, tokens, API keys) is automatically masked in request output. CLI errors fall back to plain text.

## Installation

```bash
composer require marko/errors-advanced
```

This replaces the default `marko/errors-simple` handler with a more detailed error display. The package binds `AdvancedErrorHandler` to `ErrorHandlerInterface` and registers it automatically via its `module.php` boot callback. Like errors-simple, it binds the handler as a singleton and unregisters it from its `shutdown` callback when [`Application::shutdown()`](/docs/packages/core/#shutting-down) runs.

## Usage

### For Module Developers

You do not need to do anything special --- just throw exceptions. The advanced error handler is registered automatically and catches all uncaught exceptions:

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

- **Development** --- Full error details with source code and stack traces
- **Production** --- Generic "An error occurred" page with no file paths, source code, trace, or request data
- **CLI** --- Plain text output via the text formatter

Detection uses core's [`AppEnvironment`](/docs/packages/core/#application-environment), exactly like [marko/errors-simple](/docs/packages/errors-simple/#setting-environment-mode): `MARKO_ENV` (falling back to `APP_ENV`) set to `development`, `dev` or `local` shows full details; any other value --- `production`, `staging`, or no value at all --- renders the generic page. The module binds `ErrorHandlerInterface` with a closure that wraps the container's shared `AppEnvironment` in an errors-simple `Environment` and passes it to `AdvancedErrorHandler`, which builds its `PrettyHtmlFormatter` from the same `AppEnvironment`, so both error handlers always agree.

### Status Codes and JSON

In the web SAPI the handler clears any half-rendered output buffers, then sends status `500` --- or, for an `HttpExceptionInterface` thrown outside the routing pipeline, that exception's status and headers. When the client accepts JSON (`Accept` contains `application/json` or a `+json` type, or a JSON `Content-Type` with no `Accept`), it responds with `Content-Type: application/json`: `{"message": "Server Error"}` in production, or the message, exception class, location and a trace trimmed to 20 frames in development. This matches [marko/errors-simple](/docs/packages/errors-simple/#status-codes-and-json).

Client errors such as `404`, `419` and `422` are rendered by the routing pipeline and never reach this handler --- see [Errors and HTTP Exceptions](/docs/packages/routing/#errors-and-http-exceptions).

### Sensitive Data Masking

Request data displayed in error pages is automatically masked for fields and headers whose names contain:

- `password`, `api_key`, `apikey`, `token`, `secret`, `session`, `csrf`, `auth`, `key`

The following headers are always masked as well:

- `Authorization`, `Proxy-Authorization`, `Cookie`, `X-Api-Key`, `X-Auth-Token`, `X-Csrf-Token`, `X-Xsrf-Token`

These appear as `********` in the error output. Masking is handled by `RequestDataCollector`, which normalizes names (stripping underscores and dashes, lowercasing) before matching --- so variations like `api_key`, `apiKey`, `ApiKey`, and `X-Api-Key` are all caught. Nested values (such as `?filter[status]=active`) are rendered as JSON, and list-style input with integer keys (`?ids[]=1`) is handled.

## Customization

`AdvancedErrorHandler` builds its formatters itself, so customize the page by passing your own formatter. Bind `ErrorHandlerInterface` in your app module (app bindings take priority over vendor ones):

```php title="app/web/module.php"
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Environment\AppEnvironment;
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsSimple\Environment;
use App\Web\Errors\CustomHtmlFormatter;
use Psr\Clock\ClockInterface;

return [
    'bindings' => [
        ErrorHandlerInterface::class => function (ContainerInterface $container): ErrorHandlerInterface {
            $appEnvironment = $container->get(AppEnvironment::class);

            return new AdvancedErrorHandler(
                clock: $container->get(ClockInterface::class),
                environment: new Environment(appEnvironment: $appEnvironment),
                prettyHtmlFormatter: new CustomHtmlFormatter(
                    environment: $appEnvironment,
                ),
            );
        },
    ],
];
```

A `PrettyHtmlFormatter` subclass takes the same `AppEnvironment`. When none is passed it builds one that reads the real `MARKO_ENV`/`APP_ENV`, so an unconfigured formatter fails safe to the generic page.

## API Reference

### AdvancedErrorHandler

The main error handler. Implements `ErrorHandlerInterface` from [marko/errors](/docs/packages/errors/) and delegates to the appropriate formatter based on environment. It stamps each `ErrorReport` it builds with the time from the injected PSR-20 `ClockInterface` ([`marko/clock`](/docs/packages/clock/)), so a [`FakeClock`](/docs/packages/testing/#fakeclock) freezes report timestamps in tests.

```php
use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\ErrorReport;
use Psr\Clock\ClockInterface;

class AdvancedErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        ClockInterface $clock, // stamps each ErrorReport
        ?Environment $environment = null,
        ?FormatterInterface $prettyHtmlFormatter = null, // defaults to an environment-aware PrettyHtmlFormatter
    );

    public function handle(ErrorReport $report): void;
    public function handleException(Throwable $exception): void;
    public function handleError(int $level, string $message, string $file, int $line): bool;
    public function register(): void;
    public function unregister(): void;
}
```

### PrettyHtmlFormatter

Renders the rich HTML error page in development and a safe generic page in production. Implements `FormatterInterface` from [marko/errors](/docs/packages/errors/).

```php
use Marko\Core\Environment\AppEnvironment;
use Marko\Errors\Contracts\FormatterInterface;
use Marko\Errors\ErrorReport;

class PrettyHtmlFormatter implements FormatterInterface
{
    public function __construct(
        ?SyntaxHighlighter $highlighter = null,
        AppEnvironment $environment = new AppEnvironment(), // details only for development, dev or local
        ?RequestDataCollector $requestCollector = null,
        int $contextLines = 3,
    );

    public function format(ErrorReport $report): string;
}
```

### SyntaxHighlighter

Tokenizes PHP source code and wraps tokens in styled `<span>` elements. Supports light and dark mode via `prefers-color-scheme`.

```php
class SyntaxHighlighter
{
    public function getCss(): string;
    public function highlight(string $code): string;
    public function highlightWithContext(string $code, int $errorLine, int $contextLines = 3): string;
}
```

### RequestDataCollector

Collects request data (method, URI, headers, query, POST, cookies, server info) and masks sensitive fields before display.

```php
class RequestDataCollector
{
    public function collect(): array;
}
```
