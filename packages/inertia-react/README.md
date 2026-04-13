# marko/inertia-react

React adapter scaffolding for Marko Inertia applications.

## Installation

The recommended setup path is:

```bash
marko vite:init --inertia=react
```

If `marko/inertia-react` is not installed yet, `vite:init` installs it before generating the frontend scaffold.

If you prefer to install it manually first:

```bash
composer require marko/inertia-react
marko vite:init --inertia=react
```

## What The Scaffold Generates

The React scaffold updates:

- `package.json`
- `vite.config.ts`
- `resources/js/app.ts`

The generated `resources/js/app.ts` stays intentionally small and delegates the default setup to `bootstrapMarkoInertiaReact()`.

## Basic Usage

```tsx
import { bootstrapMarkoInertiaReact } from "../../vendor/marko/inertia-react/resources/js/bootstrap";

const pages = import.meta.glob([
  "./pages/**/*.jsx",
  "./pages/**/*.tsx",
  "../../app/**/resources/js/pages/**/*.jsx",
  "../../app/**/resources/js/pages/**/*.tsx",
  "../../modules/**/resources/js/pages/**/*.jsx",
  "../../modules/**/resources/js/pages/**/*.tsx",
  "../../vendor/marko/**/resources/js/pages/**/*.jsx",
  "../../vendor/marko/**/resources/js/pages/**/*.tsx",
]);

bootstrapMarkoInertiaReact({
  pages,
});
```

## React Customization

Use `setup` when you want to control how the app is rendered:

```tsx
import { createElement, StrictMode } from "react";
import { bootstrapMarkoInertiaReact } from "../../vendor/marko/inertia-react/resources/js/bootstrap";

bootstrapMarkoInertiaReact({
  pages,
  setup: ({ root, app }) => {
    root.render(createElement(StrictMode, null, app));
  },
});
```

Use `inertia` when you want to customize `createInertiaApp()` options while keeping Marko's default page resolution:

```tsx
bootstrapMarkoInertiaReact({
  pages,
  inertia: {
    progress: {
      color: "#2563eb",
    },
  },
});
```

## Layouts

Use `defaultLayout` or `resolveLayout` for client-side layout behavior:

```tsx
import AppLayout from "@/layouts/AppLayout";
import AdminLayout from "@admin-panel/layouts/AdminLayout";

bootstrapMarkoInertiaReact({
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

If `marko/layout` is installed and a controller provides `#[Layout(...)]`, discover matching React layouts with `discoverMarkoServerLayouts()`:

```php
#[Layout(component: 'blog::AdminLayout')]
```

For Inertia-only routes, the layout can be this discovered name directly, so you only need the controller attribute and the React layout component file.

```tsx
import {
  bootstrapMarkoInertiaReact,
  discoverMarkoServerLayouts,
} from "../../vendor/marko/inertia-react/resources/js/bootstrap";

bootstrapMarkoInertiaReact({
  pages,
  serverLayouts: {
    ...discoverMarkoServerLayouts(import.meta.glob([
      "./layouts/**/*.jsx",
      "./layouts/**/*.tsx",
      "../../app/**/resources/js/layouts/**/*.jsx",
      "../../app/**/resources/js/layouts/**/*.tsx",
      "../../modules/**/resources/js/layouts/**/*.jsx",
      "../../modules/**/resources/js/layouts/**/*.tsx",
      "../../vendor/marko/**/resources/js/layouts/**/*.jsx",
      "../../vendor/marko/**/resources/js/layouts/**/*.tsx",
    ], { eager: true })),
  },
});
```

## Related Marko Docs

- [`marko/vite`](../vite/README.md) for scaffold flow, aliases, and discovery-chain rules
- [`marko/inertia`](../inertia/README.md) for server-side rendering, page names, shared props, and controller-driven layouts
