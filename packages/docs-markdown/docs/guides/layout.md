---
title: Layouts
description: Build component-based page layouts with PHP attributes, nested slots, and cross-module extensibility.
---

Marko uses PHP attributes to compose page layouts from components. Every piece of a page — the layout shell, header, sidebar, content blocks — is a component. No layout inheritance, no template extends chains.

## Creating a Layout

A layout is just a component with slots. Define it with `#[Component]` and list the slots it provides:

```php title="app/theme/src/Component/DefaultLayout.php"
<?php

declare(strict_types=1);

namespace App\Theme\Component;

use Marko\Layout\Attributes\Component;

#[Component(
    template: 'theme::layouts/default',
    slots: ['header', 'content', 'footer'],
)]
class DefaultLayout {}
```

The template name `theme::layouts/default` resolves to `resources/views/layouts/default.latte` in the `theme` module; the view driver adds the extension. The template marks where child components render with `{slot name/}` tags:

```latte title="app/theme/resources/views/layouts/default.latte"
<!doctype html>
<html>
<head><title>My App</title></head>
<body>
  <header>{slot header/}</header>
  <main>{slot content/}</main>
  <footer>{slot footer/}</footer>
</body>
</html>
```

## Attaching a Layout to a Controller

Use `#[Layout]` on a controller class to assign a layout to all its actions:

```php title="app/blog/src/Controller/PostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use App\Theme\Component\DefaultLayout;
use Marko\Layout\Attributes\Layout;
use Marko\Routing\Attributes\Get;

#[Layout(component: DefaultLayout::class)]
class PostController
{
    #[Get('/posts')]
    public function index(): void {}

    #[Get('/posts/{id}')]
    public function show(int $id): void {}
}
```

Controllers with `#[Layout]` don't return responses — the layout system assembles the page from components. The controller action still runs (for side effects like authorization), but its return value is ignored.

### Method-Level Override

A method-level `#[Layout]` overrides the class-level one:

```php
#[Layout(component: DefaultLayout::class)]
class PostController
{
    #[Get('/posts/{id}/print')]
    #[Layout(component: PrintLayout::class)]
    public function print(int $id): void
    {
        // Uses PrintLayout instead of DefaultLayout
    }
}
```

## Adding Page Components

Components declare which slot they fill and which pages they appear on:

```php title="app/blog/src/Component/PostList.php"
<?php

declare(strict_types=1);

namespace App\Blog\Component;

use Marko\Layout\Attributes\Component;

#[Component(
    template: 'blog::components/post-list',
    handle: [PostController::class, 'index'],
    slot: 'content',
)]
class PostList
{
    public function data(): array
    {
        return ['posts' => ['First Post', 'Second Post']];
    }
}
```

The `data()` method provides template variables. Route parameters are injected by name automatically:

```php title="app/blog/src/Component/PostDetail.php"
#[Component(
    template: 'blog::components/post-detail',
    handle: [PostController::class, 'show'],
    slot: 'content',
)]
class PostDetail
{
    public function data(int $id): array
    {
        return ['postId' => $id];
    }
}
```

## Handle Matching

The `handle` parameter controls which pages a component appears on:

| Handle | Matches |
|---|---|
| `'default'` | Every page |
| `'posts_*'` | All routes whose handle starts with `posts_` |
| `'posts_post_show'` | Only `PostController::show` (exact match) |
| `[PostController::class, 'show']` | Same, using class reference |

Handles are auto-generated from routes: `{first-segment}_{controller}_{method}`. The route `/posts/{id}` on `PostController::show` becomes `posts_post_show`.

A trailing `_*` enables prefix matching — `'posts_*'` catches `posts_post_index`, `posts_post_show`, `posts_comment_index`, etc. Without the wildcard, handles are matched exactly.

## Sorting Components

Multiple components in the same slot are sorted by `sortOrder` (lower renders first). The default is `0`, and negative values are supported:

```php
#[Component(
    slot: 'sidebar',
    sortOrder: 10,
)]  // Renders first
class SearchWidget {}

#[Component(
    slot: 'sidebar',
    sortOrder: 20,
)]  // Renders second
class RecentPosts {}
```

For explicit ordering, use `before` and `after` — they take priority over `sortOrder`:

```php
#[Component(
    slot: 'sidebar',
    sortOrder: 50,
    before: RecentPosts::class,
)]
class AdWidget {}
```

## Nested Slots

Components can define their own sub-slots for deeper composition:

```php title="app/blog/src/Component/PostTabs.php"
#[Component(
    template: 'blog::components/tabs',
    handle: [PostController::class, 'show'],
    slot: 'content',
    slots: ['tab.details', 'tab.reviews'],
)]
class PostTabs {}
```

```latte title="app/blog/resources/views/components/tabs.latte"
<div class="tabs">
  <div class="tab-panel">{slot tab.details/}</div>
  <div class="tab-panel">{slot tab.reviews/}</div>
</div>
```

Other components target the sub-slots using dot-notation:

```php
#[Component(
    template: 'blog::components/reviews',
    handle: 'default',
    slot: 'tab.reviews',
)]
class ReviewsList
{
    public function data(int $id): array
    {
        return ['postId' => $id];
    }
}
```

Parent components render first, then their sub-slots are filled — this happens automatically.

## Cross-Module Extensibility

Any module can inject components into any page. A shipping module can add a component to the product page without modifying the product module:

```php title="app/shipping/src/Component/ShippingEstimate.php"
#[Component(
    template: 'shipping::components/estimate',
    handle: 'products_product_show',
    slot: 'content',
)]
class ShippingEstimate
{
    public function data(int $id): array
    {
        return ['productId' => $id];
    }
}
```

To remove or reorder another module's component, use an `#[After]` plugin on `ComponentCollectorInterface::collect()`:

```php title="app/shipping/src/Plugin/RemoveVendorWidgetPlugin.php"
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Plugin;
use Marko\Layout\ComponentCollection;
use Marko\Layout\ComponentCollectorInterface;

#[Plugin(target: ComponentCollectorInterface::class)]
class RemoveVendorWidgetPlugin
{
    #[After]
    public function collect(ComponentCollection $result): ComponentCollection
    {
        $result->remove(VendorWidget::class);

        return $result;
    }
}
```

## Next Steps

- [Routing](/docs/guides/routing/) — define the routes that layouts attach to
- [Error handling](/docs/guides/error-handling/) — handle exceptions in components
- [Layout package reference](/docs/packages/layout/) — full API details
