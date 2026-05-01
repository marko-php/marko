# marko/inertia

Inertia.js protocol integration for the Marko Framework - middleware, response factory, shared data, and SSR support.

## Installation

```bash
composer require marko/inertia
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
        return $this->inertia->render($request, 'Dashboard', [
            'user' => ['name' => 'Paulo'],
        ]);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/inertia](https://marko.build/docs/packages/inertia/)
