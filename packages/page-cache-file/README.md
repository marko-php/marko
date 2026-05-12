# marko/page-cache-file

File-based full-page cache driver --- stores cached HTTP responses on disk with tag-based invalidation and atomic writes.

## Installation

```bash
composer require marko/page-cache marko/page-cache-file
```

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

Full usage, API reference, and examples: [marko/page-cache-file](https://marko.build/docs/packages/page-cache-file/)
