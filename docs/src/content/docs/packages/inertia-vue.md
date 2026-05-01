---
title: marko/inertia-vue
description: Vue 3 companion for marko/inertia - configuration defaults and a frontend marker binding for Vue.
---

Vue 3 companion for [`marko/inertia`](/docs/packages/inertia/) and [`marko/vite`](/docs/packages/vite/). It overlays the parent Inertia configuration with Vue defaults and registers a frontend marker binding so installing multiple Inertia frontend companions fails loudly.

## Installation

```bash
composer require marko/inertia-vue
```

Install the matching frontend dependencies in your app:

```bash
npm install @inertiajs/vue3 vue @vue/server-renderer @vitejs/plugin-vue vite
```

Refer to the Inertia.js docs for currently supported versions of each frontend adapter.

## Configuration

This package contributes defaults to the parent `config/inertia.php` namespace:

```php title="packages/inertia-vue/config/inertia.php"
return [
    'assetEntry' => env('INERTIA_VUE_CLIENT_ENTRY', 'app/vue-web/resources/js/app.js'),
    'ssr' => [
        'bundle' => env('INERTIA_VUE_SSR_BUNDLE', 'bootstrap/ssr/vue/ssr.js'),
    ],
];
```

| Key | Purpose |
| --- | --- |
| `assetEntry` | Vite entry used by browser-rendered Inertia responses. |
| `ssr.bundle` | Relative path to the built SSR bundle loaded by your SSR runner. |

## Usage

Render Vue-backed Inertia pages without passing an asset entry; `marko/inertia` reads it from configuration:

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

Create the client entry at `app/vue-web/resources/js/app.js`:

```js title="app/vue-web/resources/js/app.js"
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
    return pages[`./Pages/${name}.vue`];
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) }).use(plugin).mount(el);
  },
});
```

Create the SSR entry at `app/vue-web/resources/js/ssr.js`:

```js title="app/vue-web/resources/js/ssr.js"
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';

createServer((page) =>
  createInertiaApp({
    page,
    render: renderToString,
    resolve: (name) => {
      const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
      return pages[`./Pages/${name}.vue`];
    },
    setup({ App, props, plugin }) {
      return createSSRApp({ render: () => h(App, props) }).use(plugin);
    },
  }),
);
```

## API Reference

This package registers `Marko\Inertia\Frontend\InertiaFrontendInterface` to a Vue marker implementation. Installing more than one Inertia frontend companion produces the same binding conflict protection used by Marko driver siblings.

## Related Packages

- [`marko/inertia`](/docs/packages/inertia/) - renders Inertia responses and handles SSR fallback
- [`marko/vite`](/docs/packages/vite/) - resolves the configured Vue Vite entry
- [`marko/env`](/docs/packages/env/) - provides the `env()` helper used in `config/inertia.php`
