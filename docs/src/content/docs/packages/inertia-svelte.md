---
title: marko/inertia-svelte
description: Svelte companion for marko/inertia - configuration defaults for Svelte client and SSR entries.
---

Svelte companion for [`marko/inertia`](/docs/packages/inertia/) and [`marko/vite`](/docs/packages/vite/). It ships Marko configuration defaults for Svelte client and SSR entrypoints while leaving the JavaScript application code in your project.

## Installation

```bash
composer require marko/inertia-svelte
```

Install the matching frontend dependencies in your app:

```bash
npm install @inertiajs/svelte@^3.0 svelte@^5.46 @sveltejs/vite-plugin-svelte@^7.0 vite@^8.0
```

## Configuration

Configure via `config/inertia-svelte.php`:

```php title="config/inertia-svelte.php"
return [
    'clientEntry' => env('INERTIA_SVELTE_CLIENT_ENTRY', 'app/svelte-web/resources/js/app.js'),
    'ssrEntry' => env('INERTIA_SVELTE_SSR_ENTRY', 'app/svelte-web/resources/js/ssr.js'),
    'ssrBundle' => env('INERTIA_SVELTE_SSR_BUNDLE', 'bootstrap/ssr/svelte/ssr.js'),
];
```

| Key | Purpose |
| --- | --- |
| `clientEntry` | Vite entry used by browser-rendered Inertia responses. |
| `ssrEntry` | Vite entry used to build the Svelte SSR server bundle. |
| `ssrBundle` | Relative path to the built SSR bundle loaded by your SSR runner. |

## Usage

Use the configured client entry when rendering Svelte-backed Inertia pages:

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

This package is configuration-only. It does not add PHP services or bindings; `module.php` is intentionally omitted because Marko can discover the package through Composer metadata and load its `config/` directory without explicit module options.

## Related Packages

- [`marko/inertia`](/docs/packages/inertia/) - renders Inertia responses and handles SSR fallback
- [`marko/vite`](/docs/packages/vite/) - resolves the configured Svelte Vite entry
- [`marko/env`](/docs/packages/env/) - provides the `env()` helper used in `config/inertia-svelte.php`
