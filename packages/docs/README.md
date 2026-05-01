# marko/docs

Documentation search contract for Marko — defines the interface for querying Marko documentation, with interchangeable driver implementations.

## Overview

`marko/docs` is the contract package that defines how Marko documentation is searched. It ships no search implementation — install a driver instead: `marko/docs-fts` for lightweight lexical search (SQLite FTS5) or `marko/docs-vec` for hybrid semantic + lexical search (FTS5 + sqlite-vec). Both drivers implement the same `DocsSearchInterface`, so switching is a one-line dependency change.

## Installation

Install a driver (which pulls in this package automatically):

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

```php
use Marko\Docs\Contract\DocsSearchInterface;

class DocsController
{
    public function __construct(
        private DocsSearchInterface $docs,
    ) {}

    public function search(string $query): array
    {
        return $this->docs->search($query, limit: 10);
    }
}
```

## Customization

Implement `DocsSearchInterface` and register your implementation as a Preference:

```php
#[Preference(DocsSearchInterface::class)]
class MyDocsSearch implements DocsSearchInterface
{
    public function search(string $query, int $limit = 20): array { /* ... */ }
}
```

## Documentation

Full driver comparison and API reference: [marko/docs](https://marko.build/docs/packages/docs/)
