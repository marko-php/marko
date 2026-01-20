# Marko Errors Simple

The reliable fallback error handler for Marko applications.

## The Reliable Fallback Philosophy

This package exists because error handlers can fail too. Think about it: if your fancy error handler with database logging, Slack notifications, and beautiful HTML templates encounters an error... what catches *that*?

`marko/errors-simple` is designed to never fail. It has zero external dependencies beyond `marko/core` and the `marko/errors` interfaces. No database connections to time out. No external services to be unavailable. No complex templating engines to misconfigure. Just PHP, writing output.

This is the safety net that catches everything else.

## Features

### CLI: Colored Stack Traces

When running in the terminal, errors display with ANSI colors for readability. You get the exception class, message, file location, and a complete stack trace. In development mode, you also see code snippets around the error line and any context or suggestions attached to the exception.

### Web: Basic HTML Error Pages

In web requests, errors render as simple HTML pages. No CSS frameworks, no JavaScript, no external resources - just inline styles that work everywhere. This means error pages display correctly even when your asset pipeline is broken.

## Development vs Production Mode

The handler behaves differently based on your environment:

**Development mode** provides everything you need to debug:
- Full exception class name and message
- File path and line number
- Code snippet showing the error location
- Context explaining what was happening
- Suggestions for how to fix the problem
- Complete stack trace
- Previous exceptions in the chain

**Production mode** protects sensitive information:
- Generic error message
- Unique error ID for log correlation
- No file paths, stack traces, or code snippets

The philosophy here is simple: developers need details, users need reassurance.

## Environment Detection

The handler determines your environment by checking these environment variables in order:

1. `MARKO_ENV`
2. `APP_ENV`

If either is set to `dev`, `development`, or `local` (case-insensitive), you're in development mode. Anything else - including no environment variable at all - is treated as production.

This means production is the safe default. You have to explicitly opt into development mode.

## Configuration

Set your environment variable to control the mode:

```bash
# Development - full error details
MARKO_ENV=development

# Production - generic messages only
MARKO_ENV=production
```

Or use `APP_ENV` if that's your convention:

```bash
APP_ENV=local  # development mode
APP_ENV=prod   # production mode
```

## Automatic Registration

This package registers itself automatically when loaded. The module boot hook retrieves the error handler from the DI container and calls `register()`, which sets up PHP's exception handler, error handler, and shutdown function.

No manual setup required - just install the package.

## The Fallback Chain

Error handlers can be chained. When this handler registers, it stores references to any previously registered handlers. If you install a fancier error handler later, it can delegate to this one as a fallback.

Here's why this matters: imagine you're using an error handler that renders beautiful HTML with syntax highlighting, sends notifications to Slack, and logs to your monitoring service. If that handler throws an exception while processing an error, PHP's exception handler triggers again - and `marko/errors-simple` catches the failure, displaying a basic but functional error page.

The chain ensures *something* always displays, even when everything else fails.

## Using This as a Fallback

If you're building a custom error handler, you can explicitly delegate to the simple handler when things go wrong:

```php
class FancyErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private SimpleErrorHandler $fallback,
    ) {}

    public function handle(ErrorReport $report): void
    {
        try {
            // Your fancy handling logic
            $this->renderBeautifulHtmlPage($report);
            $this->notifySlack($report);
            $this->logToDatabase($report);
        } catch (Throwable $e) {
            // When fancy fails, fall back to simple
            $this->fallback->handle(ErrorReport::fromThrowable($e, Severity::Error));
        }
    }
}
```

## Customization

The handler uses two formatters that you can extend:

**TextFormatter** - Handles CLI output with colored text and code snippets. Override this to change how errors appear in the terminal.

**BasicHtmlFormatter** - Renders HTML error pages. Override this to customize the page structure while maintaining the zero-dependency philosophy.

Inject your custom formatters through the constructor:

```php
$handler = new SimpleErrorHandler(
    new Environment(),
    new CustomTextFormatter(...),
    new CustomHtmlFormatter(...),
);
```

If you need more extensive customization - like database logging or external service integration - consider implementing `ErrorHandlerInterface` directly and using this handler as your fallback.

## When to Use Simple vs Advanced

Use **errors-simple** when:
- You want a zero-configuration setup that just works
- You're building a CLI tool that needs basic error display
- You want a reliable fallback for more complex error handlers
- You're in early development and don't need fancy error pages yet

Use **errors-advanced** (when available) when:
- You want polished error pages with syntax highlighting
- You need error grouping or rate limiting
- You want to customize error display without building from scratch
- You're building a production application with specific design requirements

The two aren't mutually exclusive. Many applications use an advanced handler for day-to-day errors with the simple handler as the ultimate fallback.

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
