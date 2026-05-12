# marko/page-cache

Contracts, middleware, and CLI for full-page HTTP response caching --- cache entire responses to serve pages in microseconds.

## Installation

```bash
composer require marko/page-cache marko/page-cache-file
```

Note: This package defines contracts only --- install a driver such as `marko/page-cache-file` for storage.

## Quick Example

```php
use Marko\PageCache\Attributes\Cacheable;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class ProductController
{
    #[Get('/products/{id}')]
    #[Cacheable(ttl: 3600, tags: ['products', 'product-{id}'])]
    public function show(int $id): Response
    {
        return Response::ok($this->productRepository->find($id));
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/page-cache](https://marko.build/docs/packages/page-cache/)
