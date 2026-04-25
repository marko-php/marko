# Marko Inertia Svelte

Configuration defaults for Svelte apps built with `marko/inertia` and `marko/vite`.

## Install

```bash
composer require marko/inertia marko/inertia-svelte marko/vite
npm install @inertiajs/svelte@^3.0 svelte@^5.46 @sveltejs/vite-plugin-svelte@^7.0 vite@^8.0
```

## Files

Create the client entry at `app/svelte-web/resources/js/app.js`:

```js
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

```js
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

## Configuration

The package exposes:

- `clientEntry`: `app/svelte-web/resources/js/app.js`
- `ssrEntry`: `app/svelte-web/resources/js/ssr.js`
- `ssrBundle`: `bootstrap/ssr/svelte/ssr.js`

Override them with `INERTIA_SVELTE_CLIENT_ENTRY`, `INERTIA_SVELTE_SSR_ENTRY`, and `INERTIA_SVELTE_SSR_BUNDLE`.
