# marko/view

View and template rendering interface -- defines how controllers render templates, not which engine is used.

## Installation

```bash
composer require marko/view
```

Note: You also need a view driver. Install `marko/view-latte` for Latte template support.

## Quick Example

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class PostController
{
    public function __construct(
        private readonly ViewInterface $view,
    ) {}

    #[Get('/posts/{id}')]
    public function show(int $id): Response
    {
        return $this->view->render('blog::post/show', [
            'post' => $this->findPost($id),
        ]);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/view](https://marko.build/docs/packages/view/)
