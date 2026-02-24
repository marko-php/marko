# Plan: Pagination Package (marko/pagination)

## Created
2026-02-24

## Status
done

## Objective
Build `marko/pagination` -- a standalone pagination package providing offset-based and cursor-based paginators with API serialization, enabling efficient paginated data access for both traditional web pages and API endpoints.

## Scope

### In Scope
- Package structure (composer.json, module.php, PSR-4 autoloading)
- `PaginatorInterface` -- primary contract with items(), total(), perPage(), currentPage(), lastPage(), hasMorePages(), previousPage(), nextPage(), toArray()
- `OffsetPaginator` -- traditional offset/limit pagination with page number calculation
- `CursorPaginatorInterface` -- extended contract for cursor-based pagination
- `CursorPaginator` -- cursor-based (keyset) pagination for efficient API pagination
- `CursorInterface` and `Cursor` value object -- encodes/decodes opaque cursor strings from column/value parameters
- `PaginationException` -- three-part exception hierarchy
- `PaginationConfig` -- default per-page, max per-page, loaded from config/pagination.php
- `toArray()` serialization on both paginators -- items, meta (total, per_page, current_page, last_page), links (prev, next)
- Factory methods for creating paginators from arrays or pre-fetched query results

### Out of Scope
- Direct database query integration (pagination works with arrays/results; query builder integration is a future enhancement)
- Automatic query builder decoration (e.g., `$query->paginate()`)
- HTML pagination view rendering (template concern, future package)
- Infinite scroll helpers (frontend concern)
- Request parameter extraction (controller responsibility)

## Success Criteria
- [ ] `PaginatorInterface` defines complete contract for paginated results
- [ ] `OffsetPaginator` correctly calculates page numbers, offsets, and last page from total count
- [ ] `CursorPaginator` encodes/decodes cursors for keyset-based pagination
- [ ] `Cursor` value object provides opaque, URL-safe encoding/decoding of parameters
- [ ] Both paginators implement `toArray()` returning items, meta, and links
- [ ] `PaginationConfig` loads defaults from config file
- [ ] `PaginationException` follows three-part exception pattern
- [ ] Loud errors for invalid page numbers, per-page values, and malformed cursors
- [ ] All tests passing with >90% coverage on critical paths
- [ ] Code follows project standards (strict types, no final, constructor promotion, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | PaginatorInterface, CursorInterface, CursorPaginatorInterface, PaginationException | - | done |
| 002 | OffsetPaginator with page calculation logic | 001 | done |
| 003 | CursorPaginator with cursor encoding/decoding | 001 | done |
| 004 | toArray() serialization for API responses, meta/links generation | 002, 003 | done |
| 005 | PaginationConfig, module.php, composer.json wiring | 001 | done |

## Architecture Notes

### Package Structure
```
packages/pagination/
  src/
    Contracts/
      PaginatorInterface.php
      CursorPaginatorInterface.php
      CursorInterface.php
    Exceptions/
      PaginationException.php
    Config/
      PaginationConfig.php
    Cursor.php
    OffsetPaginator.php
    CursorPaginator.php
  config/
    pagination.php
  tests/
    Unit/
      OffsetPaginatorTest.php
      CursorPaginatorTest.php
      CursorTest.php
      PaginationExceptionTest.php
      PaginationConfigTest.php
  composer.json
  module.php
```

### PaginatorInterface Contract
```php
interface PaginatorInterface
{
    /** @return array<mixed> */
    public function items(): array;
    public function total(): int;
    public function perPage(): int;
    public function currentPage(): int;
    public function lastPage(): int;
    public function hasMorePages(): bool;
    public function previousPage(): ?int;
    public function nextPage(): ?int;

    /** @return array{items: array, meta: array, links: array} */
    public function toArray(): array;
}
```

### CursorPaginatorInterface Contract
```php
interface CursorPaginatorInterface
{
    /** @return array<mixed> */
    public function items(): array;
    public function perPage(): int;
    public function hasMorePages(): bool;
    public function cursor(): ?CursorInterface;
    public function nextCursor(): ?CursorInterface;
    public function previousCursor(): ?CursorInterface;

    /** @return array{items: array, meta: array, links: array} */
    public function toArray(): array;
}
```

### CursorInterface and Cursor Value Object
```php
interface CursorInterface
{
    /** @return array<string, mixed> */
    public function parameters(): array;
    public function parameter(string $name): mixed;
    public function encode(): string;
    public static function decode(string $encoded): static;
}
```

The `Cursor` value object holds column/value pairs (e.g., `['id' => 42]`) and encodes them to a URL-safe base64 string. This provides opaque cursors for API consumers.

### OffsetPaginator Usage
```php
// Created from pre-fetched items + total count
$paginator = new OffsetPaginator(
    items: $items,
    total: 150,
    perPage: 15,
    currentPage: 3,
);

$paginator->lastPage();     // 10
$paginator->hasMorePages(); // true
$paginator->previousPage(); // 2
$paginator->nextPage();     // 4
```

### CursorPaginator Usage
```php
// Created from items + cursor info
$paginator = new CursorPaginator(
    items: $items,
    perPage: 15,
    cursor: Cursor::decode($request->query('cursor')),
    nextCursor: new Cursor(['id' => $lastItem->id]),
    previousCursor: new Cursor(['id' => $firstItem->id]),
);

$paginator->hasMorePages();             // true
$paginator->nextCursor()->encode();     // 'eyJpZCI6NDJ9'
```

### API Serialization Format (toArray)

**OffsetPaginator:**
```json
{
    "items": [...],
    "meta": {
        "total": 150,
        "per_page": 15,
        "current_page": 3,
        "last_page": 10
    },
    "links": {
        "previous": 2,
        "next": 4
    }
}
```

**CursorPaginator:**
```json
{
    "items": [...],
    "meta": {
        "per_page": 15,
        "has_more": true
    },
    "links": {
        "previous": "eyJpZCI6MX0=",
        "next": "eyJpZCI6NDJ9"
    }
}
```

### Config File
```php
// config/pagination.php
return [
    'per_page' => 15,
    'max_per_page' => 100,
];
```

### Module Bindings
```php
// module.php
return [
    'enabled' => true,
    'bindings' => [],
];
```

No interface-to-implementation bindings needed -- paginators are created directly or via factory methods, not resolved from the container. The module.php exists for config loading.

### Relationship to Database Package
Pagination is standalone -- it works with plain arrays. A future enhancement could add `$queryBuilder->paginate()` integration, but the pagination package itself has no dependency on `marko/database`. Controllers fetch data using the query builder, calculate total count, and pass results to a paginator.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Cursor encoding reveals internal column names | Use opaque base64 encoding; document that cursors should be treated as opaque tokens |
| Large total counts on offset pagination | Document that total count queries can be expensive; cursor pagination recommended for large datasets |
| Invalid/tampered cursor strings | Validate cursor structure on decode; throw PaginationException with helpful message |
| Per-page exceeding max | PaginationConfig enforces max_per_page; loud error if exceeded |
| Off-by-one in page calculations | Comprehensive tests for edge cases: page 1, last page, single-page results, empty results |
| Future database integration coupling | Pagination is array-based; database integration would be an adapter layer, not a change to core pagination |
