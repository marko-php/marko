---
title: marko/inertia
description: Inertia.js protocol integration for the Marko Framework - middleware, response factory, shared data, partial reloads, and optional SSR support.
---

Inertia.js protocol integration for the Marko Framework. It renders Inertia page objects as JSON for Inertia requests, emits the initial HTML shell for browser visits, integrates with `marko/vite` for frontend assets, and provides shared props, lazy props, partial reloads, flash messages, asset version checks, and optional SSR.

## Installation

```bash
composer require marko/inertia
```

`marko/inertia` depends on `marko/vite`, `marko/session`, `marko/routing`, `marko/config`, and `marko/env`.

## Configuration

Configure via `config/inertia.php`:

```php title="config/inertia.php"
return [
    'version' => null,
    'assetEntry' => null,
    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://localhost:13714'),
    ],
];
```

| Key | Purpose |
| --- | --- |
| `version` | Asset version included in every Inertia page object. Set to `null` to disable version mismatch handling. |
| `assetEntry` | Default Vite entry used by `render()` when no `$assetEntry` argument is passed. Frontend companion packages (`marko/inertia-react`, `marko/inertia-vue`, `marko/inertia-svelte`) overlay this slot via `config/inertia.php` so installing one swaps the entry without code changes. |
| `ssr.enabled` | When true, the initial browser response attempts to render through the configured Inertia SSR server. |
| `ssr.url` | URL used by the SSR client when SSR is enabled. |

## Usage

Inject `Inertia` into a controller and return `render()` from route actions:

```php
use Marko\Inertia\Inertia;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class DashboardController
{
    public function __construct(
        private readonly Inertia $inertia,
    ) {}

    public function index(Request $request): Response
    {
        return $this->inertia->render($request, 'Dashboard', [
            'user' => ['name' => 'Paulo'],
            'stats' => fn (): array => ['orders' => 12],
        ]);
    }
}
```

For an Inertia request (`X-Inertia: true`), `render()` returns JSON:

```json
{
    "component": "Dashboard",
    "props": {
        "user": {
            "name": "Paulo"
        }
    },
    "url": "/",
    "version": null
}
```

For a normal browser visit, `render()` returns the initial HTML shell with the page JSON embedded and Vite head tags included.

### Asset Entries

The Vite entry resolves in this order:

1. The `$assetEntry` argument passed to `render()` (highest priority).
2. The `inertia.assetEntry` config slot, typically populated by a frontend companion package (`marko/inertia-react`, `marko/inertia-vue`, `marko/inertia-svelte`).
3. The `vite.entry` fallback from `marko/vite`.

Pass an asset entry per call to override the configured default:

```php
return $this->inertia->render(
    request: $request,
    component: 'Dashboard',
    props: ['user' => ['name' => 'Paulo']],
    assetEntry: 'app/react-web/resources/js/app.jsx',
);
```

### Shared Props

Use `share()` to attach props to every response:

```php
$this->inertia->share('auth', [
    'user' => [
        'name' => 'Paulo',
    ],
]);

$this->inertia->share([
    'appName' => 'Marko',
    'locale' => 'en',
]);
```

Shared props are merged after the built-in `errors` and `flash` props, then page props are merged last.

### Lazy Props and Partial Reloads

Props may be closures. They are evaluated for full loads and for partial reloads that include the prop:

```php
return $this->inertia->render($request, 'Reports/Index', [
    'filters' => $filters,
    'results' => fn (): array => $this->reportService->search($filters),
]);
```

The package supports `X-Inertia-Partial-Component`, `X-Inertia-Partial-Data`, and `X-Inertia-Partial-Except`. The `errors` and `flash` props remain available during partial reloads.

### Flash Messages

Flash messages are stored in the active session and exposed through the `flash` prop:

```php
$this->inertia->flash('success', 'Settings saved.');
```

Multiple messages may be flashed under the same key:

```php
$this->inertia->flash('notice', [
    'Profile updated.',
    'Notification settings saved.',
]);
```

### Redirects

Use `location()` for external or non-Inertia redirects:

```php
return $this->inertia->location('https://example.com');
```

This returns a `409` response with the `X-Inertia-Location` header.

### Middleware

Add `InertiaMiddleware` to controllers that return Inertia responses:

```php
use Marko\Inertia\Inertia;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(InertiaMiddleware::class)]
class DashboardController
{
    public function __construct(
        private readonly Inertia $inertia,
    ) {}

    #[Get('/dashboard')]
    public function index(Request $request): Response
    {
        return $this->inertia->render($request, 'Dashboard');
    }
}
```

The middleware adds `Vary: X-Inertia`, converts non-GET Inertia redirects from `302` to `303`, and returns `409` with `X-Inertia-Location` when a GET request sends a stale `X-Inertia-Version`.

### Server-Side Rendering

When `inertia.ssr.enabled` is true, the HTML shell posts the page object to `inertia.ssr.url`. A successful SSR response must return JSON with a non-empty `body` string and an optional `head` string:

```json
{
    "head": "<title>Dashboard</title>",
    "body": "<div id=\"app\">...</div>"
}
```

If the SSR transport fails or returns an unusable response, Marko falls back to the client-rendered HTML shell. Configuration errors still throw loud Marko exceptions.

## Errors

Following Marko's loud-errors principle, missing or invalid Inertia configuration throws rather than silently degrading.

`Marko\Inertia\Exceptions\InertiaConfigurationException` is thrown when:

- `inertia.version` is missing, or is not a string, number, or `null`.
- `inertia.ssr.enabled` is missing or invalid.
- `inertia.ssr.url` is missing, invalid, or empty when SSR rendering is attempted.

SSR transport failures return `null` from the transport layer so the page can fall back to client rendering.

## API Reference

```php
namespace Marko\Inertia;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class Inertia
{
    public function share(array|string $key, mixed $value = null): void;
    public function flash(string $key, string|array $value): void;
    public function render(Request $request, string $component, array $props = [], ?string $assetEntry = null): Response;
    public function location(string $url): Response;
    public function isInertiaRequest(Request $request): bool;
    public function version(): ?string;
}
```

```php
namespace Marko\Inertia\Ssr;

interface SsrTransportInterface
{
    public function post(string $url, string $body): ?string;
}
```

## Related Packages

- [`marko/vite`](/docs/packages/vite/) - renders frontend asset tags for the Inertia HTML shell
- [`marko/session`](/docs/packages/session/) - stores flash messages
- [`marko/routing`](/docs/packages/routing/) - provides requests, responses, and middleware
- [`marko/config`](/docs/packages/config/) - provides the configuration repository
- [`marko/env`](/docs/packages/env/) - provides the `env()` helper used in `config/inertia.php`
