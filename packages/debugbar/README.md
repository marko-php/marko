# Marko Debugbar

A development debugbar for the Marko Framework.

`marko/debugbar` adds a browser toolbar and stored request profiler to Marko apps so you can inspect request data, timings, memory usage, app messages, database queries, logs, rendered views, Inertia payloads, API responses, and optional configuration values while building locally.

The package is inspired by `fruitcake/laravel-debugbar`, but it is implemented around Marko's current module, plugin, and response lifecycle.

> Use this package only in development. Debugbars expose application internals by design.

## Features

- Auto-registers as a Marko module.
- Enables itself from `APP_DEBUG` or `DEBUGBAR_ENABLED`.
- Injects into HTML responses before `</body>`.
- Leaves JSON/API responses unchanged while still storing a debug snapshot.
- Adds `X-Marko-Debugbar`, `X-Marko-Debugbar-Id`, `X-Marko-Debugbar-Url`, and `Server-Timing` headers.
- Provides a `/_debugbar` profiler page for stored requests.
- Provides a `debugbar()` helper for messages and timing.
- Captures calls through Marko `ConnectionInterface`, `LoggerInterface`, and `ViewInterface`.
- Detects Marko Inertia HTML and JSON page payloads without requiring a hard Inertia package dependency.
- Masks common secrets in request and config collectors.
- Renders inline CSS and JavaScript, so no asset publishing is required.

## Installation

Install as a development dependency:

```bash
composer require marko/debugbar --dev
```

For local development against a split Marko workspace, use a path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../marko-debugbar",
      "options": {
        "symlink": true,
        "versions": {
          "marko/debugbar": "0.1.3"
        }
      }
    }
  ],
  "require-dev": {
    "marko/debugbar": "^0.1.0"
  }
}
```

Then update Composer:

```bash
composer update marko/debugbar --with-dependencies
```

If you are developing the package itself against a specific Marko release line, set the root package version when running Composer:

```bash
COMPOSER_ROOT_VERSION=0.3.1 composer install
```

## Configuration

The package ships [config/debugbar.php](config/debugbar.php). Marko merges module config automatically, and app-level `config/debugbar.php` can override it.

Minimal `.env` setup:

```env
APP_ENV=local
APP_DEBUG=true
DEBUGBAR_ENABLED=true
DEBUGBAR_THEME=auto
```

Supported environment variables:

```env
DEBUGBAR_ENABLED=true
DEBUGBAR_INJECT=true
DEBUGBAR_CAPTURE_CLI=false
DEBUGBAR_THEME=auto
DEBUGBAR_STORAGE_ENABLED=true
DEBUGBAR_STORAGE_PATH=storage/debugbar
DEBUGBAR_STORAGE_MAX_FILES=100
DEBUGBAR_ROUTE_PREFIX=_debugbar
DEBUGBAR_ROUTE_OPEN=false

DEBUGBAR_COLLECTORS_MESSAGES=true
DEBUGBAR_COLLECTORS_TIME=true
DEBUGBAR_COLLECTORS_MEMORY=true
DEBUGBAR_COLLECTORS_REQUEST=true
DEBUGBAR_COLLECTORS_RESPONSE=true
DEBUGBAR_COLLECTORS_INERTIA=true
DEBUGBAR_COLLECTORS_VIEWS=true
DEBUGBAR_COLLECTORS_DATABASE=true
DEBUGBAR_COLLECTORS_LOGS=true
DEBUGBAR_COLLECTORS_CONFIG=false

DEBUGBAR_OPTIONS_MESSAGES_TRACE=false
DEBUGBAR_OPTIONS_DATABASE_WITH_BINDINGS=true
DEBUGBAR_OPTIONS_DATABASE_SLOW_THRESHOLD_MS=100
```

Themes:

- `auto`
- `light`
- `dark`

## Collectors

### Messages

Collects messages added through the helper or `Debugbar` instance.

```php
debugbar('Loaded dashboard');
debugbar('Payment failed', 'error', ['invoice' => $invoiceId]);
```

Convenience methods are also available:

```php
debugbar()?->debug('Starting import');
debugbar()?->info('Report generated');
debugbar()?->warning('Slow external API');
debugbar()?->error('Payment failed', ['invoice' => $invoiceId]);
```

### Time

Shows total request duration and custom measures.

```php
$result = debugbar()?->measure('build report', function () {
    return buildReport();
});
```

Manual start and stop calls are available:

```php
debugbar()?->startMeasure('external api');

// ...

debugbar()?->stopMeasure('external api');
```

### Memory

Shows start, current, and peak memory usage.

### Request

Shows method, URI, query data, posted data, and request headers. Common sensitive values such as passwords, tokens, secrets, API keys, and authorization headers are masked.

### Response

Shows response type, size, headers, and a truncated preview. JSON/API responses are not modified, but the request snapshot is still written to storage and linked through the `X-Marko-Debugbar-Url` header.

### Inertia

Detects Marko Inertia responses from the final response body. It shows the component name, URL, version, prop count, prop keys, and partial reload headers when present.

This collector works for initial HTML page loads and `X-Inertia` JSON responses.

### Views

Captures renders that go through Marko's `ViewInterface`, including Latte drivers that implement the view contract.

Captured data:

- Method: `render` or `renderToString`
- Template name
- Data keys
- Start offset
- Duration
- Output size

### Database

Captures query and execute calls when database access goes through Marko's `ConnectionInterface`.

Captured data:

- Query type: `query` or `execute`
- SQL
- Bindings, configurable
- Start offset
- Duration
- Row count

The collector highlights slow queries based on `DEBUGBAR_OPTIONS_DATABASE_SLOW_THRESHOLD_MS`.

Current limitation: prepared statement execution is not captured unless it goes through `ConnectionInterface::query()` or `ConnectionInterface::execute()`.

### Logs

Captures calls through Marko's `LoggerInterface`, including PSR-style level methods and direct `log()` calls.

### Config

Disabled by default because it can expose application internals. Enable it only when needed:

```env
DEBUGBAR_COLLECTORS_CONFIG=true
```

The config collector masks common sensitive keys:

- `*.key`
- `*.password`
- `*.secret`
- `*.token`
- `*.api_key`
- `*.private_key`

Override `debugbar.options.config.masked` in app config for project-specific rules.

## Usage In A Marko App

After installation, no controller changes are required for the toolbar to render on HTML responses.

To add your own message:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class HomeController
{
    #[Get('/')]
    public function index(Request $request): Response
    {
        debugbar('Rendering home page', 'info', [
            'path' => $request->path(),
        ]);

        return Response::html('<html><body>Hello</body></html>');
    }
}
```

For Inertia pages, call the helper before returning the Inertia response:

```php
debugbar('Rendering Inertia page', 'info', [
    'component' => 'Landing',
]);

return $this->inertia->render($request, 'Landing', assetEntry: 'app/web/resources/js/app.js');
```

## Profiler UI

Every captured request gets a stable debug ID. The injected toolbar starts in a compact collapsed state with request method, duration, memory, message count, query count, log count, and URI visible. Click `Expand` or any collector tab to open the inline detail panel, and click `Collapse` to return to the compact rail.

The toolbar also includes an `Open` link to the full profiler page:

```text
/_debugbar/{id}
```

The index page lists stored requests with request summary cards and activity metrics:

```text
/_debugbar
```

The request detail page includes summary cards, collector navigation, and the complete stored collector payload. The raw dataset is also available as JSON:

```text
/_debugbar/{id}/json
```

By default, profiler routes are available only when the debugbar is enabled and the request comes from `127.0.0.1` or `::1`. Set `DEBUGBAR_ROUTE_OPEN=true` only for trusted local/dev environments.

## Package Development

Run the package quality checks:

```bash
COMPOSER_ROOT_VERSION=0.3.1 composer validate --strict
COMPOSER_ROOT_VERSION=0.3.1 composer test
COMPOSER_ROOT_VERSION=0.3.1 composer analyse
COMPOSER_ROOT_VERSION=0.3.1 composer cs:check
```

Fix style:

```bash
COMPOSER_ROOT_VERSION=0.3.1 composer cs:fix
```

## How It Works

The module binds a singleton `Marko\Debugbar\Debugbar` and boots it after Marko config and routing are available.

During boot, the debugbar starts an output buffer. It collects the final response body, stores a JSON snapshot under `storage/debugbar`, and adds debug headers. When the body looks like HTML and `DEBUGBAR_INJECT=true`, the renderer inserts the collapsed toolbar before the closing `</body>` tag. JSON-looking responses are returned unchanged.

Database, log, and view collection use Marko plugins:

- `DatabaseConnectionPlugin` targets `Marko\Database\Connection\ConnectionInterface`
- `LoggerPlugin` targets `Marko\Log\Contracts\LoggerInterface`
- `ViewPlugin` targets `Marko\View\ViewInterface`

This approach fits Marko's current extension system without requiring framework changes.

## Current Limitations

- Injection currently uses output buffering because Marko does not yet expose a formal global middleware registration API for packages.
- The profiler route prefix is configurable for generated links, but Marko route attributes are static today, so the bundled routes are registered under `/_debugbar`.
- Route name/controller metadata is not shown yet because the matched route is not exposed on the request or response.
- Ajax and redirect history are stored as individual snapshots, but the toolbar does not yet include a history dropdown.
- Prepared statements are not collected unless execution goes through `ConnectionInterface::query()` or `ConnectionInterface::execute()`.
- Assets are inline by design for the first release.

## Roadmap

- Move response injection to package-provided global middleware when Marko exposes that hook.
- Add route/controller collector data.
- Add exception collection when Marko exposes a central exception event.
- Add an Ajax/history dropdown.
- Add editor links with local/remote path mapping.
- Consider an adapter around `php-debugbar/php-debugbar` if Marko's route and asset story makes that a net simplification.

## Inspiration

This package is inspired by Laravel Debugbar's development workflow: collector configuration, response injection, request capture, and simple helper/facade-style logging.

References:

- https://github.com/fruitcake/laravel-debugbar
- https://laraveldebugbar.com/installation/

## License

MIT. See [LICENSE](LICENSE).
