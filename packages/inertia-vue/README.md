# marko/inertia-vue

Vue 3 companion for `marko/inertia` - configuration for Vue client and SSR entries.

## Installation

```bash
composer require marko/inertia-vue
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
            assetEntry: 'app/vue-web/resources/js/app.js',
        );
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/inertia-vue](https://marko.build/docs/packages/inertia-vue/)
