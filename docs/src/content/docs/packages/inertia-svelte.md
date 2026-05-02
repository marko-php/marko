---
title: marko/inertia-svelte
description: Svelte companion for marko/inertia - configuration defaults and a frontend marker binding for Svelte.
---

Svelte companion for [`marko/inertia`](/docs/packages/inertia/) and [`marko/vite`](/docs/packages/vite/). It overlays the parent Inertia configuration with Svelte defaults and registers a frontend marker binding so installing multiple Inertia frontend companions fails loudly.

## Installation

```bash
composer require marko/inertia-svelte
```

Install the matching frontend dependencies in your app:

```bash
npm install @inertiajs/svelte svelte @sveltejs/vite-plugin-svelte vite
```

Refer to the [Inertia.js docs](https://inertiajs.com/) for currently supported versions of each frontend adapter.

## Configuration

This package contributes defaults to the parent `config/inertia.php` namespace:

```php title="packages/inertia-svelte/config/inertia.php"
return [
    'assetEntry' => env('INERTIA_SVELTE_CLIENT_ENTRY', 'app/svelte-web/resources/js/app.js'),
];
```

| Key | Purpose |
| --- | --- |
| `assetEntry` | Vite entry used by browser-rendered Inertia responses. |

## Usage

Render Svelte-backed Inertia pages without passing an asset entry; `marko/inertia` reads it from configuration:

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

Create the client entry at `app/svelte-web/resources/js/app.js`:

```js title="app/svelte-web/resources/js/app.js"
import { createInertiaApp } from '@inertiajs/svelte';
import { mount } from 'svelte';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true });
    return pages[`./Pages/${name}.svelte`];
  },
  setup({ el, App, props }) {
    mount(App, { target: el, props });
  },
});
```

Create the SSR entry at `app/svelte-web/resources/js/ssr.js`:

```js title="app/svelte-web/resources/js/ssr.js"
import { createInertiaApp } from '@inertiajs/svelte';
import createServer from '@inertiajs/svelte/server';
import { render } from 'svelte/server';

createServer((page) =>
  createInertiaApp({
    page,
    resolve: (name) => {
      const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true });
      return pages[`./Pages/${name}.svelte`];
    },
    setup({ App, props }) {
      return render(App, { props });
    },
  }),
);
```

## API Reference

This package registers `Marko\Inertia\Frontend\InertiaFrontendInterface` to a Svelte marker implementation. Installing more than one Inertia frontend companion produces the same binding conflict protection used by Marko driver siblings.

## Related Packages

- [`marko/inertia`](/docs/packages/inertia/) - renders Inertia responses and handles SSR fallback
- [`marko/vite`](/docs/packages/vite/) - resolves the configured Svelte Vite entry
- [`marko/env`](/docs/packages/env/) - provides the `env()` helper used in `config/inertia.php`
