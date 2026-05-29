---
title: marko/admin-panel-twig
description: Twig templates for marko/admin-panel — provides the login, dashboard, layout, and partial views rendered by the Twig engine.
---

Twig templates for `marko/admin-panel` --- provides the login, dashboard, layout, and partial views rendered by the Twig engine. Installing this package is all that is needed to supply admin panel templates when using `marko/view-twig`; no additional configuration is required.

## Installation

```bash
composer require marko/admin-panel-twig
```

Requires [`marko/admin-panel`](/docs/packages/admin-panel/) and [`marko/view-twig`](/docs/packages/view-twig/).

## Usage

Install the package alongside `marko/admin-panel` and `marko/view-twig`:

```bash
composer require marko/admin-panel marko/admin-panel-twig marko/view-twig
```

Templates are discovered automatically via the `extra.marko.templates_for` declaration in the package's `composer.json`. The `admin-panel::` template namespace resolves to the views in this package --- for example, `admin-panel::dashboard/index` renders `resources/views/dashboard/index.twig`.

### Provided Templates

| Template name | File |
|---|---|
| `admin-panel::auth/login` | `resources/views/auth/login.twig` |
| `admin-panel::layout/base` | `resources/views/layout/base.twig` |
| `admin-panel::dashboard/index` | `resources/views/dashboard/index.twig` |
| `admin-panel::partials/sidebar` | `resources/views/partials/sidebar.twig` |
| `admin-panel::partials/flash` | `resources/views/partials/flash.twig` |

### Overriding Templates

Override any template by placing a file with the same path under your own module's `resources/views/admin-panel/` directory:

```
mymodule/
  resources/
    views/
      admin-panel/
        dashboard/
          index.twig    # Overrides the default dashboard
```

## Related Packages

- [`marko/admin-panel`](/docs/packages/admin-panel/) --- admin panel logic and routes
- [`marko/admin-panel-latte`](/docs/packages/admin-panel-latte/) --- Latte template alternative
- [`marko/view-twig`](/docs/packages/view-twig/) --- Twig rendering engine
