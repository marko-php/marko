# marko/inertia-svelte

Svelte adapter scaffolding for Marko Inertia applications.

## Installation

The recommended setup path is:

```bash
marko vite:init --inertia=svelte
```

If `marko/inertia-svelte` is not installed yet, `vite:init` installs it before generating the frontend scaffold.

If you prefer to install it manually first:

```bash
composer require marko/inertia-svelte
marko vite:init --inertia=svelte
```

## What The Scaffold Generates

The Svelte scaffold updates:

- `package.json`
- `vite.config.ts`
- `resources/js/app.ts`

The generated `resources/js/app.ts` stays intentionally small and delegates the default setup to `bootstrapMarkoInertiaSvelte()`.

## Basic Usage

```ts
import { bootstrapMarkoInertiaSvelte } from "../../vendor/marko/inertia-svelte/resources/js/bootstrap";

const pages = import.meta.glob([
  "./pages/**/*.svelte",
  "../../app/**/resources/js/pages/**/*.svelte",
  "../../modules/**/resources/js/pages/**/*.svelte",
  "../../vendor/marko/**/resources/js/pages/**/*.svelte",
]);

bootstrapMarkoInertiaSvelte({
  pages,
});
```

## Svelte Customization

Use `setup` when you want to control how the app mounts:

```ts
import { mount } from "svelte";
import { bootstrapMarkoInertiaSvelte } from "../../vendor/marko/inertia-svelte/resources/js/bootstrap";

bootstrapMarkoInertiaSvelte({
  pages,
  setup: ({ el, App, props }) => {
    mount(App as never, {
      target: el,
      props,
    });
  },
});
```

Use `inertia` when you want to customize `createInertiaApp()` options while keeping Marko's default page resolution:

```ts
bootstrapMarkoInertiaSvelte({
  pages,
  inertia: {
    progress: {
      color: "#ea580c",
    },
  },
});
```

## Layouts

Use `defaultLayout` or `resolveLayout` for client-side layout behavior:

```ts
import AppLayout from "@/layouts/AppLayout.svelte";
import AdminLayout from "@admin-panel/layouts/AdminLayout.svelte";

bootstrapMarkoInertiaSvelte({
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

If `marko/layout` is installed and a controller provides `#[Layout(...)]`, discover matching Svelte layouts with `discoverMarkoServerLayouts()`:

```php
#[Layout(component: 'blog::AdminLayout')]
```

For Inertia-only routes, the layout can be this discovered name directly, so you only need the controller attribute and the Svelte layout component file.

```ts
import {
  bootstrapMarkoInertiaSvelte,
  discoverMarkoServerLayouts,
} from "../../vendor/marko/inertia-svelte/resources/js/bootstrap";

bootstrapMarkoInertiaSvelte({
  pages,
  serverLayouts: {
    ...discoverMarkoServerLayouts(import.meta.glob([
      "./layouts/**/*.svelte",
      "../../app/**/resources/js/layouts/**/*.svelte",
      "../../modules/**/resources/js/layouts/**/*.svelte",
      "../../vendor/marko/**/resources/js/layouts/**/*.svelte",
    ], { eager: true })),
  },
});
```

## Related Marko Docs

- [`marko/vite`](../vite/README.md) for scaffold flow, aliases, and discovery-chain rules
- [`marko/inertia`](../inertia/README.md) for server-side rendering, page names, shared props, and controller-driven layouts
