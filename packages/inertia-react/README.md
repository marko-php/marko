# marko/inertia-react

React companion for `marko/inertia` - configuration for React client and SSR entries.

## Installation

```bash
composer require marko/inertia-react
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
            assetEntry: 'app/react-web/resources/js/app.jsx',
        );
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/inertia-react](https://marko.build/docs/packages/inertia-react/)
