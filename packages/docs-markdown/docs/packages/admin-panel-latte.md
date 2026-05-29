---
title: marko/admin-panel-latte
description: Latte templates for marko/admin-panel — provides the login, dashboard, layout, and partial views rendered by the Latte engine.
---

Latte templates for `marko/admin-panel` --- provides the login, dashboard, layout, and partial views rendered by the Latte engine. Installing this package is all that is needed to supply admin panel templates when using `marko/view-latte`; no additional configuration is required.

## Installation

```bash
composer require marko/admin-panel-latte
```

Requires [`marko/admin-panel`](/docs/packages/admin-panel/) and [`marko/view-latte`](/docs/packages/view-latte/).

## Usage

Install the package alongside `marko/admin-panel` and `marko/view-latte`:

```bash
composer require marko/admin-panel marko/admin-panel-latte marko/view-latte
```

Templates are discovered automatically via the `extra.marko.templates_for` declaration in the package's `composer.json`. The `admin-panel::` template namespace resolves to the views in this package --- for example, `admin-panel::dashboard/index` renders `resources/views/dashboard/index.latte`.

### Provided Templates

| Template name | File |
|---|---|
| `admin-panel::auth/login` | `resources/views/auth/login.latte` |
| `admin-panel::layout/base` | `resources/views/layout/base.latte` |
| `admin-panel::dashboard/index` | `resources/views/dashboard/index.latte` |
| `admin-panel::partials/sidebar` | `resources/views/partials/sidebar.latte` |
| `admin-panel::partials/flash` | `resources/views/partials/flash.latte` |

### Overriding Templates

Override any template by placing a file with the same path under your own module's `resources/views/admin-panel/` directory:

```
mymodule/
  resources/
    views/
      admin-panel/
        dashboard/
          index.latte    # Overrides the default dashboard
```

## Related Packages

- [`marko/admin-panel`](/docs/packages/admin-panel/) --- admin panel logic and routes
- [`marko/admin-panel-twig`](/docs/packages/admin-panel-twig/) --- Twig template alternative
- [`marko/view-latte`](/docs/packages/view-latte/) --- Latte rendering engine
