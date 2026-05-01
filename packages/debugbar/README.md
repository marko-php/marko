# marko/debugbar

Development debugbar and request profiler for the Marko Framework.

## Installation

```bash
composer require marko/debugbar --dev
```

## Quick Example

With `APP_DEBUG=true` (or `DEBUGBAR_ENABLED=true`), the debugbar auto-injects into HTML responses and stores a snapshot for every request. Add custom messages and timings from anywhere in your app:

```php
debugbar('Rendering dashboard', 'info', ['user_id' => $user->id]);

$report = debugbar()?->measure('build report', fn () => $this->reports->build());
```

Open `/_debugbar` to browse stored requests, or follow the `X-Marko-Debugbar-Url` header on JSON responses for the per-request profiler page.

## Documentation

Full configuration, collectors, and API reference: [marko/debugbar](https://marko.build/docs/packages/debugbar/)
