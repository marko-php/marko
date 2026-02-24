# Marko Pagination

Offset and cursor pagination with API-ready serialization--paginate any result set without coupling to your data layer.

## Overview

Pagination provides two strategies: offset-based (traditional page numbers) and cursor-based (for large or real-time datasets). Both produce structured arrays ready for JSON API responses. A config helper enforces per-page limits so clients cannot request unbounded result sets.

## Installation

```bash
composer require marko/pagination
```

## Usage

### Offset Pagination

The most common approach--pass items, total count, per-page, and current page:

```php
use Marko\Pagination\OffsetPaginator;

$paginator = new OffsetPaginator(
    items: $items,
    total: 150,
    perPage: 15,
    currentPage: 3,
);

$paginator->items();        // Items for page 3
$paginator->hasMorePages(); // true
$paginator->lastPage();     // 10
$paginator->nextPage();     // 4
$paginator->previousPage(); // 2
```

### Cursor Pagination

For large datasets or infinite scroll, use cursor-based pagination:

```php
use Marko\Pagination\Cursor;
use Marko\Pagination\CursorPaginator;

$cursor = new Cursor(['id' => 42]);

$paginator = new CursorPaginator(
    items: $items,
    perPage: 20,
    cursor: $cursor,
    nextCursor: new Cursor(['id' => 62]),
);

$paginator->hasMorePages();           // true
$paginator->nextCursor()->encode();   // Base64-encoded cursor string
```

### Decoding Cursors from Requests

Parse a cursor string from a query parameter:

```php
use Marko\Pagination\Cursor;

$cursor = Cursor::decode($request->getQueryParams()['cursor']);
$lastId = $cursor->parameter('id');
```

### API Response Serialization

Both paginators serialize to a structured array:

```php
$response = $paginator->toArray();
// ['items' => [...], 'meta' => [...], 'links' => [...]]
```

### Clamping Per-Page Values

Use `PaginationConfig` to enforce server-side limits on client-requested page sizes:

```php
use Marko\Pagination\Config\PaginationConfig;

public function __construct(
    private PaginationConfig $config,
) {}

public function list(
    int $requestedPerPage,
): array {
    $perPage = $this->config->clampPerPage($requestedPerPage);
    // $perPage is between 1 and max_per_page
}
```

## Customization

Replace `OffsetPaginator` or `CursorPaginator` via Preferences to add custom serialization or metadata:

```php
use Marko\Core\Attributes\Preference;
use Marko\Pagination\OffsetPaginator;

#[Preference(replaces: OffsetPaginator::class)]
class MyPaginator extends OffsetPaginator
{
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['meta']['has_previous'] = $this->previousPage() !== null;

        return $data;
    }
}
```

## API Reference

### PaginatorInterface (Offset)

```php
public function items(): array;
public function total(): int;
public function perPage(): int;
public function currentPage(): int;
public function lastPage(): int;
public function hasMorePages(): bool;
public function previousPage(): ?int;
public function nextPage(): ?int;
public function toArray(): array;
```

### CursorPaginatorInterface

```php
public function items(): array;
public function perPage(): int;
public function hasMorePages(): bool;
public function cursor(): ?CursorInterface;
public function nextCursor(): ?CursorInterface;
public function previousCursor(): ?CursorInterface;
public function toArray(): array;
```

### CursorInterface

```php
public function parameters(): array;
public function parameter(string $name): mixed;
public function encode(): string;
public static function decode(string $encoded): static;
```

### PaginationConfig

```php
public function perPage(): int;
public function maxPerPage(): int;
public function clampPerPage(int $requested): int;
```
