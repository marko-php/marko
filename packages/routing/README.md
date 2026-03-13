# Marko Routing

Attribute-based routing with automatic conflict detection---define routes on controller methods, not in separate files.

## Installation

```bash
composer require marko/routing
```

## Quick Example

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Response;

class ProductController
{
    #[Get('/products')]
    public function index(): Response
    {
        return new Response('Product list');
    }

    #[Get('/products/{id}')]
    public function show(int $id): Response
    {
        return new Response("Product $id");
    }

    #[Post('/products')]
    public function store(): Response
    {
        return new Response('Created', 201);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/routing](https://marko.build/docs/packages/routing/)
