---
title: marko/search
description: Generic search abstraction — add full-text search to any entity with a database driver included and support for Elasticsearch, Meilisearch, and Typesense.
---

Generic search abstraction --- add full-text search to any entity with a database driver included and support for Elasticsearch, Meilisearch, and Typesense. Search provides a driver-based architecture for querying any entity with filtering, sorting, and pagination. Entities declare their searchable fields and weights by implementing `SearchableInterface`. The `DatabaseSearchDriver` executes SQL LIKE queries across those fields. Additional drivers for Elasticsearch, Meilisearch, and Typesense can be wired in without changing application code.

## Installation

```bash
composer require marko/search
```

## Usage

### Implementing SearchableInterface

Make any entity searchable by implementing `getSearchableFields()`. Return a map of field names to boost weights --- higher weights increase relevance:

```php
use Marko\Search\Contracts\SearchableInterface;

class Post implements SearchableInterface
{
    public function getSearchableFields(): array
    {
        return [
            'title' => 2.0,
            'body' => 1.0,
            'tags' => 1.5,
        ];
    }
}
```

### Building Search Criteria

`SearchCriteria` is an immutable value object built with a fluent interface:

```php
use Marko\Search\Value\FilterOperator;
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchFilter;

$criteria = SearchCriteria::create('php tutorial')
    ->withFilter(new SearchFilter(
        field: 'status',
        operator: FilterOperator::Equals,
        value: 'published',
    ))
    ->withFilter(new SearchFilter(
        field: 'category',
        operator: FilterOperator::In,
        value: ['php', 'backend'],
    ))
    ->withSort('created_at', 'desc')
    ->withPage(2)
    ->withPerPage(10);
```

### Executing a Search

Instantiate `DatabaseSearchDriver` with a database connection, table name, and searchable entity, then call `search()`:

```php
use Marko\Search\Driver\DatabaseSearchDriver;
use Marko\Search\Value\SearchCriteria;

$driver = new DatabaseSearchDriver(
    connection: $connection,
    tableName: 'posts',
    searchable: new Post(),
);

$result = $driver->search(
    query: 'php tutorial',
    criteria: $criteria,
);
```

### Working with Results

`SearchResult` provides total count and pagination metadata:

```php
if ($result->isEmpty()) {
    // No results found
}

foreach ($result->items as $row) {
    echo $row['title'];
}

echo "Page {$result->page} of {$result->totalPages()}";
echo "Showing {$result->perPage} of {$result->total} total results";
```

### Filtering with All Operators

```php
use Marko\Search\Value\FilterOperator;
use Marko\Search\Value\SearchFilter;

// Exact match
new SearchFilter('status', FilterOperator::Equals, 'published');

// Exclude a value
new SearchFilter('status', FilterOperator::NotEquals, 'draft');

// Numeric comparisons
new SearchFilter('view_count', FilterOperator::GreaterThan, 100);
new SearchFilter('price', FilterOperator::LessThan, 50.00);

// Match against a list
new SearchFilter('category', FilterOperator::In, ['php', 'backend']);

// Partial match (pass the % wildcards yourself)
new SearchFilter('title', FilterOperator::Like, '%tutorial%');
```

## Customization

Swap the search driver via a [Preference](/docs/packages/core/) without changing call sites:

```php
use Marko\Core\Attributes\Preference;
use Marko\Search\Contracts\SearchInterface;
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchResult;

#[Preference(replaces: SearchInterface::class)]
class MeilisearchDriver implements SearchInterface
{
    public function search(
        string $query,
        SearchCriteria $criteria,
    ): SearchResult {
        // Meilisearch implementation
    }
}
```

## API Reference

### SearchInterface

```php
use Marko\Search\Contracts\SearchInterface;
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchResult;

public function search(
    string $query,
    SearchCriteria $criteria,
): SearchResult;
```

### SearchableInterface

```php
use Marko\Search\Contracts\SearchableInterface;

/** @return array<string, float> field => weight */
public function getSearchableFields(): array;
```

### SearchCriteria

```php
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchFilter;

public static function create(string $query = ''): static;
public function withFilter(SearchFilter $searchFilter): static;
public function withSort(string $field, string $direction = 'asc'): static;
public function withPage(int $page): static;
public function withPerPage(int $perPage): static;

// Properties (readonly)
public string $query;
public array $filters;       // SearchFilter[]
public string $sortBy;
public string $sortDirection;
public int $page;
public int $perPage;
```

### SearchFilter

```php
use Marko\Search\Value\FilterOperator;
use Marko\Search\Value\SearchFilter;

public function __construct(
    public string $field,
    public FilterOperator $operator,
    public mixed $value,
);
```

### FilterOperator

| Case | Value | Description |
|---|---|---|
| `Equals` | `equals` | Exact match (`=`) |
| `NotEquals` | `not_equals` | Exclude value (`!=`) |
| `GreaterThan` | `greater_than` | Numeric greater than (`>`) |
| `LessThan` | `less_than` | Numeric less than (`<`) |
| `In` | `in` | Match any value in a list (`IN`) |
| `Like` | `like` | Partial string match (`LIKE`) |

### SearchResult

```php
use Marko\Search\Value\SearchResult;

public function __construct(
    public array $items,
    public int $total,
    public string $query,
    public int $page,
    public int $perPage,
);

public function totalPages(): int;
public function isEmpty(): bool;
```

### DatabaseSearchDriver

```php
use Marko\Database\Contracts\ConnectionInterface;
use Marko\Search\Contracts\SearchableInterface;
use Marko\Search\Driver\DatabaseSearchDriver;

public function __construct(
    private readonly ConnectionInterface $connection,
    private readonly string $tableName,
    private readonly SearchableInterface $searchable,
);
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `SearchException` | Base exception for all search errors --- includes `getContext()` and `getSuggestion()` methods |
