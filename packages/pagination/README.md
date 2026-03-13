# marko/pagination

Offset and cursor pagination with API-ready serialization--paginate any result set without coupling to your data layer.

## Installation

```bash
composer require marko/pagination
```

## Quick Example

```php
use Marko\Pagination\OffsetPaginator;

$paginator = new OffsetPaginator(
    items: $items,
    total: 150,
    perPage: 15,
    currentPage: 3,
);

$paginator->hasMorePages(); // true
$paginator->lastPage();     // 10
$paginator->toArray();      // ['items' => [...], 'meta' => [...], 'links' => [...]]
```

## Documentation

Full usage, API reference, and examples: [marko/pagination](https://marko.build/docs/packages/pagination/)
