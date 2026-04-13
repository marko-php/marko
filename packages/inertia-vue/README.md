# marko/inertia-vue

Vue adapter scaffolding for Marko Inertia applications.

## Installation

The recommended setup path is:

```bash
marko vite:init --inertia=vue
```

If `marko/inertia-vue` is not installed yet, `vite:init` installs it before generating the frontend scaffold.

If you prefer to install it manually first:

```bash
composer require marko/inertia-vue
marko vite:init --inertia=vue
```

## What The Scaffold Generates

The Vue scaffold updates:

- `package.json`
- `vite.config.ts`
- `resources/js/app.ts`

The generated `resources/js/app.ts` stays intentionally small and delegates the default setup to `bootstrapMarkoInertiaVue()`.

## Basic Usage

```ts
import { bootstrapMarkoInertiaVue } from "../../vendor/marko/inertia-vue/resources/js/bootstrap";

const pages = import.meta.glob([
  "./pages/**/*.vue",
  "../../app/**/resources/js/pages/**/*.vue",
  "../../modules/**/resources/js/pages/**/*.vue",
  "../../vendor/marko/**/resources/js/pages/**/*.vue",
]);

bootstrapMarkoInertiaVue({
  pages,
});
```

## Vue Customization

Use `setup` when you want to register plugins, globals, or other Vue app behavior:

```ts
import { bootstrapMarkoInertiaVue } from "../../vendor/marko/inertia-vue/resources/js/bootstrap";

bootstrapMarkoInertiaVue({
  pages,
  setup: ({ app, el }) => {
    app.config.globalProperties.$appName = "Marko";
    app.mount(el);
  },
});
```

Use `inertia` when you want to customize `createInertiaApp()` options while keeping Marko's default page resolution:

```ts
bootstrapMarkoInertiaVue({
  pages,
  inertia: {
    progress: {
      color: "#0f766e",
    },
  },
});
```

## Layouts

Use `defaultLayout` or `resolveLayout` for client-side layout behavior:

```ts
import AppLayout from "@/layouts/AppLayout.vue";
import AdminLayout from "@admin-panel/layouts/AdminLayout.vue";

bootstrapMarkoInertiaVue({
  pages,
  defaultLayout: AppLayout,
  resolveLayout: ({ moduleName, componentPath }) => {
    if (moduleName === "admin-panel" || componentPath.startsWith("Admin/")) {
      return AdminLayout;
    }

    return AppLayout;
  },
});
```

If `marko/layout` is installed and a controller provides `#[Layout(...)]`, discover matching Vue layouts with `discoverMarkoServerLayouts()`:

```php
#[Layout(component: 'blog::AdminLayout')]
```

For Inertia-only routes, the layout can be this discovered name directly, so you only need the controller attribute and the Vue layout component file.

```ts
import {
  bootstrapMarkoInertiaVue,
  discoverMarkoServerLayouts,
} from "../../vendor/marko/inertia-vue/resources/js/bootstrap";

bootstrapMarkoInertiaVue({
  pages,
  serverLayouts: {
    ...discoverMarkoServerLayouts(import.meta.glob([
      "./layouts/**/*.vue",
      "../../app/**/resources/js/layouts/**/*.vue",
      "../../modules/**/resources/js/layouts/**/*.vue",
      "../../vendor/marko/**/resources/js/layouts/**/*.vue",
    ], { eager: true })),
  },
});
```

## Related Marko Docs

- [`marko/vite`](../vite/README.md) for scaffold flow, aliases, and discovery-chain rules
- [`marko/inertia`](../inertia/README.md) for server-side rendering, page names, shared props, and controller-driven layouts
