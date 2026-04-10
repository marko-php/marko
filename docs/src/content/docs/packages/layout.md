---
title: marko/layout
description: Attribute-driven layout system where everything is a component — layouts, sections, and widgets composed via #[Component] with cross-module extensibility.
---

Everything is a component. Layouts, page sections, and widgets are composed via `#[Component]` attributes and assembled automatically per route. Cross-module injection uses the same Plugin/Preference system as the rest of Marko.

## Installation

```bash
composer require marko/layout
```

## Usage

### Define a Layout Component

A layout component is the page root. It declares the top-level slots that other components fill:

```php title="DefaultLayout.php"
use Marko\Layout\Attributes\Component;

#[Component(
    template: 'layouts/default.html',
    slots: ['header', 'content', 'footer'],
)]
class DefaultLayout {}
```

```html title="layouts/default.html"
<!doctype html>
<html>
<body>
  {slot header}{/slot}
  {slot content}{/slot}
  {slot footer}{/slot}
</body>
</html>
```

### Attach a Layout to a Controller

```php title="ProductController.php"
use Marko\Layout\Attributes\Layout;
use Marko\Routing\Attributes\Get;

#[Layout(component: DefaultLayout::class)]
class ProductController
{
    #[Get('/products/{id}')]
    public function show(int $id): void
    {
        // Side effects only — data comes from component data() methods
    }
}
```

### Add Page Components

Components declare which slot they render into and which routes they appear on:

```php title="ProductContent.php"
use Marko\Layout\Attributes\Component;

#[Component(
    template: 'components/product-content.html',
    handle: 'products_*',
    slot: 'content',   // matches all products_* routes
)]
class ProductContent
{
    public function data(int $id): array
    {
        return ['productId' => $id];
    }
}
```

Route parameters are injected into `data()` by name automatically.

### Nested Slots

Components can define their own sub-slots for deeper composition:

```php
#[Component(
    template: 'components/tabs.html',
    handle: 'products_product_show',
    slot: 'content',
    slots: ['tab.details', 'tab.reviews'],
)]
class ProductTabs {}
```

Child components target these sub-slots using dot-notation:

```php
#[Component(
    template: 'components/reviews.html',
    handle: 'default',
    slot: 'tab.reviews',
)]
class ReviewsTab {}
```

### Cross-Module Injection

Any module can modify the component collection via an `#[After]` plugin on `ComponentCollectorInterface::collect()`:

```php
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Plugin;
use Marko\Layout\ComponentCollection;
use Marko\Layout\ComponentCollectorInterface;

#[Plugin(target: ComponentCollectorInterface::class)]
class CustomizeLayoutPlugin
{
    #[After]
    public function collect(ComponentCollection $result): ComponentCollection
    {
        $result->remove(OtherComponent::class);
        $result->move(MyComponent::class, 'sidebar', sortOrder: 5);

        return $result;
    }
}
```

### Handle Matching

Handles control which pages a component appears on:

| Handle value | Matches |
|---|---|
| `'default'` | Every page |
| `'products_*'` | All routes whose handle starts with `products_` |
| `'products_product_show'` | Only `ProductController::show` (exact match) |
| `[ProductController::class, 'show']` | Resolved to the exact handle |

### LayoutMiddleware

Register `LayoutMiddleware` in your application middleware stack. When a controller has a `#[Layout]` attribute, it delegates rendering to `LayoutProcessor` automatically.

## Customization

Override any component via [Preferences](/docs/packages/core/) to swap implementations without modifying vendor code.

## API Reference

```php
// Attributes
#[Component(
    template: string,
    handle: string|array,
    slot: ?string,
    slots: array,
    sortOrder: int,
    before: ?string,
    after: ?string,
)]
#[Layout(component: string)]

// ComponentCollection
$componentCollection->add(ComponentDefinition $definition): void
$componentCollection->remove(string $className): void
$componentCollection->get(string $className): ComponentDefinition
$componentCollection->move(string $className, string $slot, ?int $sortOrder = null): void
$componentCollection->forSlot(string $slot): array
$componentCollection->count(): int

// LayoutProcessor
$processor->process(string $controllerClass, string $action, string $routePath, array $routeParameters, Request $request): Response
```
