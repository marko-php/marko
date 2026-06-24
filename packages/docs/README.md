# marko/docs

Documentation search contract for Marko — defines the interface for querying Marko documentation, with interchangeable driver implementations.

## Overview

`marko/docs` is the contract package that defines how Marko documentation is searched. It ships no search implementation — install a driver instead: `marko/docs-fts` for lightweight lexical search (SQLite FTS5). A driver implements `DocsSearchInterface`, so apps depend on the contract and stay decoupled from the backend.

## Installation

Install a driver (which pulls in this package automatically):

```bash
composer require marko/docs-fts
```

Or install the contract alone if you are building a custom driver:

```bash
composer require marko/docs
```

## Usage

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
        return $this->docs->search(new DocsQuery($term, limit: 10));
    }
}
```

## Customization

Implement `DocsSearchInterface` and register your implementation as a Preference:

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

## Documentation

Full driver comparison and API reference: [marko/docs](https://marko.build/docs/packages/docs/)
