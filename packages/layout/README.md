# marko/layout

Composable layout system with slot-based injection---define layouts and components with attributes, not configuration files.

## Installation

```bash
composer require marko/layout
```

## Quick Example

Define a layout component with named slots:

```php
use Marko\Layout\Attributes\Component;

#[Component(
    template: 'blog::layout/default',
    slots: ['content', 'sidebar'],
)]
class DefaultLayout {}
```

Point a controller action at that layout:

```php
use Marko\Layout\Attributes\Layout;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class PostController
{
    #[Get('/posts/{id}')]
    #[Layout(DefaultLayout::class)]
    public function show(int $id): Response
    {
        return new Response();
    }
}
```

Inject a page component into a slot from any module:

```php
#[Component(
    template: 'blog::post/body',
    handle: 'post_show',
    slot: 'content',
)]
class PostBodyComponent {}
```

The layout middleware assembles all components targeting the current handle and renders the final page---no wiring required.

## Documentation

Full usage, API reference, and examples: [marko/layout](https://marko.build/docs/packages/layout/)
