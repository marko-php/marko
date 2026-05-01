# marko/vite

Vite integration for the Marko Framework — asset manifest resolution and dev-server detection for production builds and HMR.

## Installation

```bash
composer require marko/vite
```

## Quick Example

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

In dev (`vite.useDevServer = true`), this emits `<script type="module">` tags pointing at the Vite dev server. In production, it reads `public/build/.vite/manifest.json` and emits hashed `<script>`, `<link rel="stylesheet">`, and `<link rel="modulepreload">` tags.

## Documentation

Full usage, API reference, and examples: [marko/vite](https://marko.build/docs/packages/vite/)
