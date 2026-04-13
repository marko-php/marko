# marko/inertia

Server-side Inertia integration for Marko applications, including page rendering, shared props, middleware, asset bootstrapping, and optional SSR support.

## Installation

```bash
composer require marko/inertia
```

Pair it with one client adapter scaffold:

```bash
marko vite:init --inertia=vue
marko vite:init --inertia=react
marko vite:init --inertia=svelte
```

If the selected adapter package is not installed yet, `vite:init` installs it before generating the frontend files.

## Quick Example

```php
use Marko\Inertia\Interfaces\InertiaInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class DashboardController
{
    public function __construct(
        private readonly InertiaInterface $inertia,
    ) {}

    #[Get('/dashboard')]
    public function index(Request $request): Response
    {
        return $this->inertia->render(
            'Dashboard/Index',
            ['title' => 'Dashboard'],
            $request,
        );
    }
}
```

## How Marko Resolves Pages

Marko's Inertia integration is built around Marko's module structure.

Root pages use plain component names:

```php
$this->inertia->render('Dashboard/Index');
```

Module pages use `module::component` names:

```php
$this->inertia->render('blog::Posts/Index');
```

The generated client bootstrap typically discovers pages from:

1. `resources/js/pages`
2. `app/*/resources/js/pages`
3. `modules/**/resources/js/pages`
4. `vendor/marko/**/resources/js/pages`

## Shared Props And Middleware

`marko/inertia` includes middleware support for shared props and request handling. Extend the middleware when you want shared data on every page:

```php
<?php

declare(strict_types=1);

namespace App\MyApp\Http\Middleware;

use Marko\Inertia\Middleware\HandleInertiaRequests;
use Marko\Routing\Http\Request;

class ShareInertiaData extends HandleInertiaRequests
{
    protected function share(Request $request): array
    {
        return [
            'appName' => 'Marko',
        ];
    }
}
```

## Controller-Driven Layouts

If `marko/layout` is installed, controllers can provide layout metadata with `#[Layout(...)]`. Inertia passes that metadata to the client adapter, where Vue, React, or Svelte can map it to frontend layout components.

Server-provided layout names follow Marko's module naming style:

- root layout: `RootAdminLayout`
- module layout: `blog::AdminLayout`

For Inertia-only routes, `#[Layout(...)]` can point directly at one of those discovered names instead of a PHP layout component class:

```php
#[Layout(component: 'blog::AdminLayout')]
```

That keeps the setup to the frontend layout component plus the controller attribute. If you also need Marko's server-rendered `marko/layout` pipeline, keep using a real PHP component class with `#[Component(...)]`.

The generated adapter bootstraps support:

- page-local layouts
- default layouts
- resolved layouts by module/component path
- discovered server layouts via `discoverMarkoServerLayouts(...)`

For the client-side adapter setup, see:

- [`marko/inertia-vue`](../inertia-vue/README.md)
- [`marko/inertia-react`](../inertia-react/README.md)
- [`marko/inertia-svelte`](../inertia-svelte/README.md)

## Vite Integration

`marko/inertia` is designed to work with `marko/vite`. The easiest setup path is:

```bash
composer require marko/inertia
composer require marko/vite
marko vite:init --inertia=vue
```

See [`marko/vite`](../vite/README.md) for:

- scaffold flow
- Vite aliases
- discovery chain details
- development and production asset handling

## SSR

`marko/inertia` includes optional SSR support through the configured SSR gateway and bundle settings in `inertia` config.

Use SSR when you need server-rendered initial HTML for Inertia pages, but it is optional. The default client-side bootstrap works without SSR.

## Documentation

Full usage, configuration, and API reference: [marko/inertia](https://marko.build/docs/packages/inertia/)
