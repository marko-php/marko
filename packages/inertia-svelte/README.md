# marko/inertia-svelte

Svelte companion for `marko/inertia` - configuration for Svelte client and SSR entries.

## Installation

```bash
composer require marko/inertia-svelte
```

## Quick Example

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
        return $this->inertia->render(
            request: $request,
            component: 'Dashboard',
            assetEntry: 'app/svelte-web/resources/js/app.js',
        );
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/inertia-svelte](https://marko.build/docs/packages/inertia-svelte/)
