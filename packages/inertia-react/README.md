# marko/inertia-react

React companion for `marko/inertia` - configuration defaults and a frontend marker binding for React.

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
        );
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/inertia-react](https://marko.build/docs/packages/inertia-react/)
