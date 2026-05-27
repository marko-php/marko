# marko/admin-panel-latte

Latte templates for marko/admin-panel — provides the login, dashboard, layout, and partial views rendered by the Latte engine.

## Installation

```bash
composer require marko/admin-panel-latte
```

Requires `marko/admin-panel` and `marko/view-latte`. Installing this package automatically makes the admin panel templates available — no additional configuration needed.

## Quick Example

```bash
# Install the admin panel with Latte templates
composer require marko/admin-panel marko/admin-panel-latte marko/view-latte
```

Templates are resolved automatically via the `admin-panel::` namespace (e.g., `admin-panel::dashboard/index`).

## Documentation

Full usage, API reference, and examples: [marko/admin-panel](https://marko.build/docs/packages/admin-panel/)
