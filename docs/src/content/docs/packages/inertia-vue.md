---
title: marko/inertia-vue
description: Vue 3 companion for marko/inertia - configuration defaults for Vue client and SSR entries.
---

Vue 3 companion for [`marko/inertia`](/docs/packages/inertia/) and [`marko/vite`](/docs/packages/vite/). It ships Marko configuration defaults for Vue client and SSR entrypoints while leaving the JavaScript application code in your project.

## Installation

```bash
composer require marko/inertia-vue
```

Install the matching frontend dependencies in your app:

```bash
npm install @inertiajs/vue3@^3.0 vue@^3.5 @vue/server-renderer@^3.5 @vitejs/plugin-vue@^6.0 vite@^8.0
```

## Configuration

Configure via `config/inertia-vue.php`:

```php title="config/inertia-vue.php"
return [
    'clientEntry' => env('INERTIA_VUE_CLIENT_ENTRY', 'app/vue-web/resources/js/app.js'),
    'ssrEntry' => env('INERTIA_VUE_SSR_ENTRY', 'app/vue-web/resources/js/ssr.js'),
    'ssrBundle' => env('INERTIA_VUE_SSR_BUNDLE', 'bootstrap/ssr/vue/ssr.js'),
];
```

| Key | Purpose |
| --- | --- |
| `clientEntry` | Vite entry used by browser-rendered Inertia responses. |
| `ssrEntry` | Vite entry used to build the Vue SSR server bundle. |
| `ssrBundle` | Relative path to the built SSR bundle loaded by your SSR runner. |

## Usage

Use the configured client entry when rendering Vue-backed Inertia pages:

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

This package is configuration-only. It does not add PHP services or bindings; `module.php` is intentionally omitted because Marko can discover the package through Composer metadata and load its `config/` directory without explicit module options.

## Related Packages

- [`marko/inertia`](/docs/packages/inertia/) - renders Inertia responses and handles SSR fallback
- [`marko/vite`](/docs/packages/vite/) - resolves the configured Vue Vite entry
- [`marko/env`](/docs/packages/env/) - provides the `env()` helper used in `config/inertia-vue.php`
