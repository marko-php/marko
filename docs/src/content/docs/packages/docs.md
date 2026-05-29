---
title: marko/docs
description: Documentation search contract — the interface for querying Marko documentation, with interchangeable drivers.
---

Documentation search contract for Marko — defines the interface for querying Marko documentation, with interchangeable driver implementations. `marko/docs` ships **no search implementation**; it defines `DocsSearchInterface` and the value objects that drivers return. Install a driver to get a working search backend.

**This package defines a contract only.** Install a driver for implementation:

- `marko/docs-fts` — lightweight lexical search (SQLite FTS5)
- `marko/docs-vec` — hybrid semantic + lexical search (FTS5 + sqlite-vec)

Both drivers implement the same `DocsSearchInterface`, so switching is a one-line dependency change.

## Installation

Install a driver (which pulls in this contract automatically):

```bash
# Lightweight lexical search
composer require marko/docs-fts

# Hybrid semantic + lexical search
composer require marko/docs-vec
```

Or install the contract alone if you are building a custom driver:

```bash
composer require marko/docs
```

## Usage

Type-hint `DocsSearchInterface` and let the installed driver handle the backend:

```php
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\ValueObject\DocsQuery;

class DocsController
{
    public function __construct(
        private DocsSearchInterface $docs,
    ) {}

    public function search(string $term): array
    {
        // Returns list<DocsResult>
        return $this->docs->search(new DocsQuery($term, limit: 10));
    }
}
```

## Customization

Implement `DocsSearchInterface` and register your implementation as a `#[Preference]`:

```php
#[Preference(DocsSearchInterface::class)]
class MyDocsSearch implements DocsSearchInterface
{
    public function search(DocsQuery $query): array { /* ... */ }
    public function getPage(string $id): DocsPage { /* ... */ }
    public function listNav(): array { /* ... */ }
    public function driverName(): string { return 'custom'; }
}
```

## API Reference

### `DocsSearchInterface`

| Method | Returns | Description |
|--------|---------|-------------|
| `search(DocsQuery $query)` | `list<DocsResult>` | Run a search; throws `DocsException` on failure |
| `getPage(string $id)` | `DocsPage` | Fetch a single page by id |
| `listNav()` | `list<DocsNavEntry>` | The documentation navigation tree |
| `driverName()` | `string` | Identifier of the active driver (e.g. `fts`, `vec`) |

### Value objects

- **`DocsQuery`** — `query: string`, `limit: int = 10`
- **`DocsResult`** — `pageId: string`, `title: string`, `excerpt: string`, `score: float`
- **`DocsPage`** — `id: string`, `title: string`, `content: string`, `path: string`
- **`DocsNavEntry`** — `id: string`, `title: string`, `path: string`, `depth: int = 0`

## Related Packages

- [`marko/docs-fts`](/docs/packages/docs-fts/) — lexical search driver
- [`marko/docs-vec`](/docs/packages/docs-vec/) — hybrid semantic search driver
- `marko/docs-markdown` — the canonical documentation content the drivers index
