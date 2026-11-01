---
title: marko/debugbar
description: Development debugbar and request profiler for Marko apps with collectors for requests, responses, timing, memory, messages, database queries, logs, views, and Inertia payloads.
---

Development debugbar and request profiler for the Marko Framework. Auto-injects a toolbar into HTML responses and stores a snapshot for every request, with collectors for request data, response, timing, memory, messages, database queries, logs, rendered views, Inertia payloads, and (opt-in) configuration. Inspired by `fruitcake/laravel-debugbar`, but built around Marko's module, plugin, and response lifecycle.

> Use this package only in development. Debugbars expose application internals by design. As a safety net, the debugbar refuses to run when the app environment is production (see [Production hard-stop](#production-hard-stop)).

## Installation

```bash
composer require marko/debugbar --dev
```

The package auto-registers as a Marko module. By default it enables itself when `APP_DEBUG=true` and the app environment (`MARKO_ENV`/`APP_ENV`) is not production.

## Configuration

Configure via `config/debugbar.php`. The package ships defaults; an app-level `config/debugbar.php` can override them.

```php title="config/debugbar.php"
use Marko\Config\Env;

return [
    'enabled' => Env::bool('DEBUGBAR_ENABLED', Env::bool('APP_DEBUG', false)),
    'allow_production' => Env::bool('DEBUGBAR_ALLOW_PRODUCTION', false),
    'inject' => Env::bool('DEBUGBAR_INJECT', true),
    'capture_cli' => Env::bool('DEBUGBAR_CAPTURE_CLI', false),
    'theme' => Env::string('DEBUGBAR_THEME', 'auto'),
    'route' => [
        'open' => Env::bool('DEBUGBAR_ROUTE_OPEN', false),
        'allowed_ips' => ['127.0.0.1', '::1'],
        'trusted_proxies' => [],
    ],
    'storage' => [
        'enabled' => Env::bool('DEBUGBAR_STORAGE_ENABLED', true),
        'path' => Env::string('DEBUGBAR_STORAGE_PATH', 'storage/debugbar'),
        'max_files' => Env::int('DEBUGBAR_STORAGE_MAX_FILES', 100, min: 0),
    ],
    'collectors' => [
        'messages' => Env::bool('DEBUGBAR_COLLECTORS_MESSAGES', true),
        'time' => Env::bool('DEBUGBAR_COLLECTORS_TIME', true),
        'memory' => Env::bool('DEBUGBAR_COLLECTORS_MEMORY', true),
        'request' => Env::bool('DEBUGBAR_COLLECTORS_REQUEST', true),
        'response' => Env::bool('DEBUGBAR_COLLECTORS_RESPONSE', true),
        'inertia' => Env::bool('DEBUGBAR_COLLECTORS_INERTIA', true),
        'views' => Env::bool('DEBUGBAR_COLLECTORS_VIEWS', true),
        'database' => Env::bool('DEBUGBAR_COLLECTORS_DATABASE', true),
        'logs' => Env::bool('DEBUGBAR_COLLECTORS_LOGS', true),
        'config' => Env::bool('DEBUGBAR_COLLECTORS_CONFIG', false),
    ],
    'options' => [
        'messages' => [
            'trace' => Env::bool('DEBUGBAR_OPTIONS_MESSAGES_TRACE', false),
        ],
        'config' => [
            'masked' => [
                'key',
                '*.key',
                'password',
                '*.password',
                'secret',
                '*.secret',
                'token',
                '*.token',
                'dsn',
                '*.dsn',
                '*.api_key',
                '*.private_key',
            ],
        ],
        'database' => [
            'with_bindings' => Env::bool('DEBUGBAR_OPTIONS_DATABASE_WITH_BINDINGS', true),
            'slow_threshold_ms' => Env::int('DEBUGBAR_OPTIONS_DATABASE_SLOW_THRESHOLD_MS', 100, min: 0),
        ],
    ],
];
```

Boolean variables accept `true`, `false`, `1`, `0`, `yes`, `no`, `on` and `off` (case-insensitive). Any other value throws a `ConfigException` at boot.

| Key | Purpose |
| --- | --- |
| `enabled` | Master switch. Defaults to `APP_DEBUG`. Ignored in production unless `allow_production` is true. |
| `allow_production` | Explicit override for the production hard-stop. Leave `false`. |
| `inject` | When true, the toolbar is injected into HTML responses before `</body>`. |
| `capture_cli` | When true, captures CLI invocations as well as HTTP requests. |
| `theme` | Toolbar theme: `auto`, `light`, or `dark`. |
| `route.open` | When false, the toolbar and profiler routes are restricted to `route.allowed_ips`. Set to `true` only on trusted networks. |
| `route.allowed_ips` | Client IPs allowed to see the toolbar and reach `/_debugbar`. |
| `route.trusted_proxies` | Reverse-proxy IPs whose `X-Forwarded-For`/`Forwarded` headers are honoured. Empty by default. |
| `storage.path` | Directory where request snapshots are written. A relative path resolves against the project root; an absolute path (Unix, Windows drive or UNC) is used as given. |
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

By default, profiler routes are available only when the debugbar is enabled and the request comes from `127.0.0.1` or `::1`. Set `DEBUGBAR_ROUTE_OPEN=true` only for trusted local/dev environments. See [Access control](#access-control) for the full rules.

### Access control

The same rules gate the injected toolbar, the debug response headers, snapshot storage, and every `/_debugbar` route. A request that fails them gets an untouched response (and a `404` from profiler routes):

1. The debugbar must be enabled and the environment must not be production (see below).
2. If `route.open` is true, every client is allowed. Otherwise:
3. A missing or invalid `REMOTE_ADDR` is denied.
4. If the request carries `X-Forwarded-For` or `Forwarded`, it is denied unless `REMOTE_ADDR` is listed in `route.trusted_proxies`. Behind a trusted proxy, the right-most forwarded hop that is not itself a trusted proxy is treated as the client; any hop that is not a valid IP denies.
5. The client IP must be in `route.allowed_ips`.

Rule 4 matters when a reverse proxy (nginx, Caddy, Traefik) runs on the same host as PHP: every request then arrives from loopback, so without it any internet visitor would pass the loopback allowlist. If you develop behind such a proxy, list it explicitly:

```php title="config/debugbar.php"
'route' => [
    'allowed_ips' => ['127.0.0.1', '::1'],
    'trusted_proxies' => ['127.0.0.1'],
],
```

CLI capture (`capture_cli`) has no HTTP client, so the IP rules do not apply to it.

### Production hard-stop

`Debugbar::isEnabled()` and the profiler routes return false/`404` whenever `AppEnvironment::isProduction()` is true, even if `DEBUGBAR_ENABLED` or `APP_DEBUG` is on. An unset `MARKO_ENV`/`APP_ENV` counts as production. This protects apps that ship `marko/debugbar` because `composer install` ran without `--no-dev`, or that copied a development `.env`.

To run the debugbar in a production-named environment anyway (for example a locked-down staging box), set `DEBUGBAR_ALLOW_PRODUCTION=true`. The IP rules above still apply.

### Response headers

When capturing, every response carries:

- `X-Marko-Debugbar: true`
- `X-Marko-Debugbar-Id: {id}`
- `X-Marko-Debugbar-Url: {profiler URL}`
- `Server-Timing: marko;dur={ms};desc="Marko"`

### Sensitive value masking

Request headers, `$_GET`, `$_POST`, message context and log context are masked when a key contains `authorization`, `password`, `token`, `secret`, `api-key`, `api_key` or `cookie` (case-insensitive, nested arrays included). Sensitive query-string values are also masked inside the captured request URI.

The `Cookie` request header and `Set-Cookie` response headers keep cookie names and attributes but every value is replaced with `[masked]`, so session IDs never appear in the toolbar or in stored snapshots.

Snapshots are written with mode `0600` inside a storage directory created with mode `0700`, so other local users cannot read them. A directory created by an older version keeps its existing mode; tighten it with `chmod 700 storage/debugbar` if needed.

The config collector default mask list covers both top-level and nested keys:

- `key`, `*.key`
- `password`, `*.password`
- `secret`, `*.secret`
- `token`, `*.token`
- `dsn`, `*.dsn`
- `*.api_key`
- `*.private_key`

Top-level entries (e.g. `key`, `password`) match root config keys directly. Wildcard entries (e.g. `*.key`) match the same name at any nesting depth. Override via `debugbar.options.config.masked` in app config for project-specific rules.

## API Reference

```php
namespace Marko\Debugbar;

class Debugbar
{
    public static function current(): ?self;
    public static function forgetCurrent(): void;

    public function boot(): void;
    public function isEnabled(): bool;
    public function clientAllowed(): bool;
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
