---
title: marko/vite
description: Vite integration for the Marko Framework — asset manifest resolution and dev-server detection for production builds and HMR.
---

Vite integration for the Marko Framework. Resolves the [Vite](https://vitejs.dev/) build manifest in production and emits dev-server tags during development, with React Fast Refresh support detected from entry filenames.

## Installation

```bash
composer require marko/vite
```

## Configuration

Configure via `config/vite.php`:

```php title="config/vite.php"
return [
    'entry' => env('VITE_ENTRY', ''),
    'buildDirectory' => 'build',
    'manifestFilename' => '.vite/manifest.json',
    'devServerUrl' => env('VITE_DEV_SERVER_URL', ''),
    'devServerStylesheets' => [],
    'useDevServer' => env('VITE_USE_DEV_SERVER', env('APP_ENV', 'local') === 'local'),
];
```

| Key | Purpose |
| --- | --- |
| `entry` | Default entry point relative to your project root (e.g. `app/web/resources/js/app.js`). Required when calling `headTags()` without an argument. |
| `buildDirectory` | Subdirectory under `public/` where Vite writes built assets. Defaults to `build`. |
| `manifestFilename` | Manifest path relative to `public/{buildDirectory}/`. Defaults to `.vite/manifest.json`. |
| `devServerUrl` | URL of the running Vite dev server. Required when `useDevServer` is true. |
| `devServerStylesheets` | Stylesheets served directly by Vite during dev (e.g. Tailwind entrypoints). |
| `useDevServer` | When true, emit dev-server tags. When false, read the production manifest. |

## Usage

Inject `Vite` and call `headTags()` to render the `<script>` and `<link>` tags for the document head:

```php
use Marko\Vite\Vite;

class LayoutController
{
    public function __construct(
        private readonly Vite $vite,
    ) {}

    public function head(): string
    {
        return $this->vite->headTags('app/web/resources/js/app.js');
    }
}
```

Pass an entry path to override the configured default. With no argument, the value of `vite.entry` is used.

### Development mode

When `vite.useDevServer` is `true`, `headTags()` emits dev-server tags:

```html
<link rel="stylesheet" href="http://localhost:5173/app/web/resources/css/app.css">
<script type="module" src="http://localhost:5173/@vite/client"></script>
<script type="module" src="http://localhost:5173/app/web/resources/js/app.js"></script>
```

If the entry filename ends in `.jsx` or `.tsx`, a [React Fast Refresh](https://github.com/facebook/react/tree/main/packages/react-refresh) preamble is automatically injected before the entry script.

### Production mode

When `useDevServer` is `false`, `headTags()` reads `public/{buildDirectory}/{manifestFilename}` and emits hashed asset tags:

```html
<link rel="stylesheet" href="/build/assets/app.456.css">
<script type="module" src="/build/assets/app.123.js"></script>
<link rel="modulepreload" href="/build/assets/shared.789.js">
```

Imported chunks are walked recursively — their CSS files and JS modules are emitted as `<link rel="stylesheet">` and `<link rel="modulepreload">` respectively, so HTTP/2 push and module preloading work correctly.

## Errors

Following Marko's loud-errors principle, configuration and manifest issues throw rather than silently degrade.

`Marko\Vite\Exceptions\ViteConfigurationException` is thrown when:

- `vite.entry` is empty and no entry argument is passed to `headTags()`.
- `vite.devServerUrl` is empty and `useDevServer` is true.
- A required config key is missing or has the wrong type.

`Marko\Vite\Exceptions\ViteManifestException` is thrown in production mode when:

- The manifest file does not exist (run `npm run build`).
- The manifest cannot be read or is not valid JSON.
- The configured entry is not present in the manifest.
- The entry's `file` field is missing or empty.

## API Reference

```php
namespace Marko\Vite;

class Vite
{
    public function headTags(?string $entry = null): string;
    public function useDevServer(): bool;
}
```

## Related Packages

- [`marko/config`](/docs/packages/config/) — provides the configuration repository
- [`marko/env`](/docs/packages/env/) — provides the `env()` helper used in `config/vite.php`
