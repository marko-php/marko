---
title: marko/debugbar
description: Development debugbar and request profiler for Marko apps with collectors for requests, responses, timing, memory, messages, database queries, logs, views, and Inertia payloads.
---

Development debugbar and request profiler for the Marko Framework. Auto-injects a toolbar into HTML responses and stores a snapshot for every request, with collectors for request data, response, timing, memory, messages, database queries, logs, rendered views, Inertia payloads, and (opt-in) configuration. Inspired by `fruitcake/laravel-debugbar`, but built around Marko's module, plugin, and response lifecycle.

> Use this package only in development. Debugbars expose application internals by design.

## Installation

```bash
composer require marko/debugbar --dev
```

The package auto-registers as a Marko module. By default it enables itself when `APP_DEBUG=true`.

## Configuration

Configure via `config/debugbar.php`. The package ships defaults; an app-level `config/debugbar.php` can override them.

```php title="config/debugbar.php"
return [
    'enabled' => env('DEBUGBAR_ENABLED', env('APP_DEBUG', false)),
    'inject' => env('DEBUGBAR_INJECT', true),
    'capture_cli' => env('DEBUGBAR_CAPTURE_CLI', false),
    'theme' => env('DEBUGBAR_THEME', 'auto'),
    'route' => [
        'prefix' => env('DEBUGBAR_ROUTE_PREFIX', '_debugbar'),
        'open' => env('DEBUGBAR_ROUTE_OPEN', false),
        'allowed_ips' => ['127.0.0.1', '::1'],
    ],
    'storage' => [
        'enabled' => env('DEBUGBAR_STORAGE_ENABLED', true),
        'path' => env('DEBUGBAR_STORAGE_PATH', 'storage/debugbar'),
        'max_files' => env('DEBUGBAR_STORAGE_MAX_FILES', 100),
    ],
    'collectors' => [
        'messages' => env('DEBUGBAR_COLLECTORS_MESSAGES', true),
        'time' => env('DEBUGBAR_COLLECTORS_TIME', true),
        'memory' => env('DEBUGBAR_COLLECTORS_MEMORY', true),
        'request' => env('DEBUGBAR_COLLECTORS_REQUEST', true),
        'response' => env('DEBUGBAR_COLLECTORS_RESPONSE', true),
        'inertia' => env('DEBUGBAR_COLLECTORS_INERTIA', true),
        'views' => env('DEBUGBAR_COLLECTORS_VIEWS', true),
        'database' => env('DEBUGBAR_COLLECTORS_DATABASE', true),
        'logs' => env('DEBUGBAR_COLLECTORS_LOGS', true),
        'config' => env('DEBUGBAR_COLLECTORS_CONFIG', false),
    ],
    'options' => [
        'messages' => [
            'trace' => env('DEBUGBAR_OPTIONS_MESSAGES_TRACE', false),
        ],
        'database' => [
            'with_bindings' => env('DEBUGBAR_OPTIONS_DATABASE_WITH_BINDINGS', true),
            'slow_threshold_ms' => env('DEBUGBAR_OPTIONS_DATABASE_SLOW_THRESHOLD_MS', 100),
        ],
    ],
];
```

| Key | Purpose |
| --- | --- |
| `enabled` | Master switch. Defaults to `APP_DEBUG`. |
| `inject` | When true, the toolbar is injected into HTML responses before `</body>`. |
| `capture_cli` | When true, captures CLI invocations as well as HTTP requests. |
| `theme` | Toolbar theme: `auto`, `light`, or `dark`. |
| `route.prefix` | Path prefix for the profiler UI. Default `_debugbar`. |
| `route.open` | When false, profiler routes are restricted to `route.allowed_ips`. Set to `true` only on trusted networks. |
| `storage.path` | Directory (relative to project root) where request snapshots are written. |
| `storage.max_files` | Snapshot retention cap. Older files are pruned. |
| `collectors.*` | Toggle individual collectors. The `config` collector is off by default. |
| `options.database.slow_threshold_ms` | Queries slower than this are highlighted. |

## Usage

The debugbar boots automatically with the framework. No controller wiring is required for the toolbar to render on HTML responses.

### Adding messages

Use the `debugbar()` helper to log a message against the current request:

```php
debugbar('Loaded dashboard');
debugbar('Payment failed', 'error', ['invoice' => $invoiceId]);
```

PSR-style level methods are also available on the instance:

```php
debugbar()?->debug('Starting import');
debugbar()?->info('Report generated');
debugbar()?->warning('Slow external API');
debugbar()?->error('Payment failed', ['invoice' => $invoiceId]);
```

### Measuring time

```php
$result = debugbar()?->measure('build report', fn () => buildReport());

debugbar()?->startMeasure('external api');
// ...
debugbar()?->stopMeasure('external api');
```

### Database, logs, and views

The package ships three plugins (`DatabaseConnectionPlugin`, `LoggerPlugin`, `ViewPlugin`) that intercept calls through Marko's `ConnectionInterface`, `LoggerInterface`, and `ViewInterface`. Anything that goes through those interfaces is captured automatically — no controller changes required.

Captured query data: type (`query` or `execute`), SQL, bindings (configurable), start offset, duration, and row count. Queries above `options.database.slow_threshold_ms` are highlighted.

Current limitation: prepared statement execution is captured only when it goes through `ConnectionInterface::query()` or `ConnectionInterface::execute()`.

### Inertia

The Inertia collector detects Marko Inertia HTML and `X-Inertia` JSON payloads from the final response body — no hard dependency on an Inertia package. It surfaces component name, URL, version, prop count, prop keys, and partial-reload headers when present.

### Profiler UI

Every captured request gets a stable debug ID. The injected toolbar starts in a compact rail showing method, duration, memory, message count, query count, log count, and URI. `Expand` opens the inline detail panel.

The toolbar links to the per-request profiler page:

```text
/_debugbar/{id}
```

The index lists stored requests:

```text
/_debugbar
```

Raw collector dataset for a request:

```text
/_debugbar/{id}/json
```

JSON/API responses are not modified, but the snapshot is still written and the per-request URL is exposed via the `X-Marko-Debugbar-Url` response header.

By default, profiler routes are available only when the debugbar is enabled and the request comes from `127.0.0.1` or `::1`. Set `DEBUGBAR_ROUTE_OPEN=true` only for trusted local/dev environments.

### Response headers

When capturing, every response carries:

- `X-Marko-Debugbar: true`
- `X-Marko-Debugbar-Id: {id}`
- `X-Marko-Debugbar-Url: {profiler URL}`
- `Server-Timing: marko;dur={ms};desc="Marko"`

### Sensitive value masking

The request and config collectors mask common sensitive keys. The config collector default mask list:

- `*.key`
- `*.password`
- `*.secret`
- `*.token`
- `*.api_key`
- `*.private_key`

Override via `debugbar.options.config.masked` in app config for project-specific rules.

## API Reference

```php
namespace Marko\Debugbar;

class Debugbar
{
    public static function current(): ?self;
    public static function forgetCurrent(): void;

    public function boot(): void;
    public function isEnabled(): bool;
    public function isCapturing(): bool;
    public function id(): string;
    public function profilerUrl(): string;

    public function addMessage(string $message, string $level = 'info', array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;

    public function startMeasure(string $name): void;
    public function stopMeasure(string $name): void;
    public function measure(string $name, Closure $callback): mixed;

    public function inject(string $html): string;
    public function collect(?string $responseBody = null): array;
}
```

The `debugbar()` helper is registered globally:

```php
function debugbar(?string $message = null, string $level = 'info', array $context = []): ?Debugbar;
```

It returns the current `Debugbar` instance (or `null` when no request is active) and adds the message when one is provided.

## Related Packages

- [`marko/config`](/docs/packages/config/) — provides the configuration repository
- [`marko/database`](/docs/packages/database/) — `ConnectionInterface` is what the database collector intercepts
- [`marko/log`](/docs/packages/log/) — `LoggerInterface` is what the logs collector intercepts
- [`marko/view`](/docs/packages/view/) — `ViewInterface` is what the views collector intercepts
- [`marko/routing`](/docs/packages/routing/) — registers the `/_debugbar` profiler routes
