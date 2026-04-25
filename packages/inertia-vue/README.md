# Marko Inertia Vue

Configuration defaults for Vue 3 apps built with `marko/inertia` and `marko/vite`.

## Install

```bash
composer require marko/inertia marko/inertia-vue marko/vite
npm install @inertiajs/vue3@^3.0 vue@^3.5 @vue/server-renderer@^3.5 @vitejs/plugin-vue@^6.0 vite@^8.0
```

## Files

Create the client entry at `app/vue-web/resources/js/app.js`:

```js
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

```js
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

## Configuration

The package exposes:

- `clientEntry`: `app/vue-web/resources/js/app.js`
- `ssrEntry`: `app/vue-web/resources/js/ssr.js`
- `ssrBundle`: `bootstrap/ssr/vue/ssr.js`

Override them with `INERTIA_VUE_CLIENT_ENTRY`, `INERTIA_VUE_SSR_ENTRY`, and `INERTIA_VUE_SSR_BUNDLE`.
