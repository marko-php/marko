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
    template: 'layouts/default',
    slots: ['header', 'content', 'footer'],
)]
class DefaultLayout {}
```

```latte title="resources/views/layouts/default.latte"
<!doctype html>
<html>
<body>
  {slot header/}
  {slot content/}
  {slot footer/}
</body>
</html>
```

With the Twig driver, the same layout uses the `slot()` function:

```twig title="resources/views/layouts/default.twig"
<!doctype html>
<html>
<body>
  {{ slot('header') }}
  {{ slot('content') }}
  {{ slot('footer') }}
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
    template: 'components/product-content',
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
    template: 'components/tabs',
    handle: 'products_product_show',
    slot: 'content',
    slots: ['tab.details', 'tab.reviews'],
)]
class ProductTabs {}
```

Child components target these sub-slots using dot-notation:

```php
#[Component(
    template: 'components/reviews',
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

`marko/layout` registers `LayoutMiddleware` as global middleware, so there is nothing to wire up. When a controller has a `#[Layout]` attribute, it delegates rendering to `LayoutProcessor` automatically.

`LayoutMiddleware` is marked [`#[RunsInnermost]`](/docs/packages/routing/#innermost-global-middleware): unlike other global middleware, it runs **after** every route middleware, directly around the controller action. That has two consequences:

- **Denied requests never render a layout.** Route middleware such as authentication, `#[Can]` or a rate limiter answers before `LayoutMiddleware` runs. And `LayoutMiddleware` only replaces a **successful** controller result (a 2xx response without a `Location` header) with the rendered layout; any other result --- a 404 thrown by the controller, a redirect it returns --- passes through untouched.
- **Headers and cookies survive.** Route middleware wraps the rendered layout response, so a `Set-Cookie`, a `Cache-Control` or any custom header it adds on the way out reaches the client. Headers and cookies the controller put on its own response are copied onto the layout response too. Where both set the same header or cookie, the layout's wins, and `Content-Type` and `Content-Length` always describe the rendered layout.

```php title="app/web/src/Http/Middleware/NoStoreMiddleware.php"
class NoStoreMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // $next() returns the rendered layout for a #[Layout] route.
        return $next($request)->withHeader('Cache-Control', 'no-store');
    }
}
```

Global middleware (sessions, security headers, page cache) still wraps everything, including the layout. `CsrfMiddleware` from [`marko/security`](/docs/packages/security/) is also `#[RunsInnermost]`: it runs after your route middleware but still wraps the layout.

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
