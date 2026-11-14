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

### Declaring Filterable, Sortable and Selectable Columns

`DatabaseSearchDriver` only touches columns the entity declares. By default the searchable fields are the only columns a search can filter on, sort by, or return. Implement any of three optional interfaces to widen a list --- each one replaces the default for that list, so name every column it should allow:

```php
use Marko\Search\Contracts\FilterableInterface;
use Marko\Search\Contracts\SearchableInterface;
use Marko\Search\Contracts\SelectableInterface;
use Marko\Search\Contracts\SortableInterface;

class Post implements SearchableInterface, FilterableInterface, SortableInterface, SelectableInterface
{
    public function getSearchableFields(): array
    {
        return ['title' => 2.0, 'body' => 1.0];
    }

    // Columns a SearchFilter may target
    public function getFilterableFields(): array
    {
        return ['status', 'category', 'author_id'];
    }

    // Columns SearchCriteria::withSort() may name
    public function getSortableFields(): array
    {
        return ['title', 'created_at'];
    }

    // Columns returned in each result row (the driver never runs SELECT *)
    public function getSelectableFields(): array
    {
        return ['id', 'title', 'slug', 'created_at'];
    }
}
```

| Interface | Method | Default when not implemented |
|---|---|---|
| `FilterableInterface` | `getFilterableFields()` | The searchable fields |
| `SortableInterface` | `getSortableFields()` | The searchable fields |
| `SelectableInterface` | `getSelectableFields()` | The searchable fields |

A filter or sort on any other column throws `SearchException` before any SQL runs, so mapping request parameters straight into `SearchCriteria` cannot filter on, sort by, or read back a column such as `password_hash` or `reset_token`. Without `SelectableInterface` the rows do not include the primary key, so declare `id` there when a listing links to each row.

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

Instantiate `DatabaseSearchDriver` with a database connection, table name, searchable entity, and `SearchConfig` (inject it, or build it from the `ConfigRepositoryInterface`), then call `search()`:

```php
use Marko\Search\Config\SearchConfig;
use Marko\Search\Driver\DatabaseSearchDriver;
use Marko\Search\Value\SearchCriteria;

$driver = new DatabaseSearchDriver(
    connection: $connection,
    tableName: 'posts',
    searchable: new Post(),
    config: $searchConfig,
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

An `In` filter with an empty list matches no rows. The `Like` filter's value is used as a pattern as-is, so build it in code rather than passing raw user input; the search query itself is always matched literally (see below).

### Search Terms and Pagination Limits

The search query is matched literally: `%`, `_` and the escape character `!` are escaped (with `ESCAPE '!'`, which every supported database spells the same way), so searching for `100%` finds rows containing `100%` rather than every row.

The page must be 1 or greater; `withPage(0)` throws `SearchException`. The per-page count is clamped to between 1 and `max_per_page`, and `SearchResult::$perPage` reports the clamped value.

## Configuration

`config/search.php`:

```php
return [
    // DatabaseSearchDriver clamps SearchCriteria::$perPage to between 1 and this value.
    'max_per_page' => 100,
];
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

### FilterableInterface, SortableInterface, SelectableInterface

```php
use Marko\Search\Contracts\FilterableInterface;
use Marko\Search\Contracts\SelectableInterface;
use Marko\Search\Contracts\SortableInterface;

/** @return array<string> */
public function getFilterableFields(): array;   // FilterableInterface

/** @return array<string> */
public function getSortableFields(): array;     // SortableInterface

/** @return array<string> */
public function getSelectableFields(): array;   // SelectableInterface
```

### SearchConfig

```php
use Marko\Search\Config\SearchConfig;

public function maxPerPage(): int;                 // search.max_per_page
public function clampPerPage(int $requested): int; // between 1 and maxPerPage()
```

### SearchCriteria

```php
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchFilter;

public static function create(string $query = ''): static;
public function withFilter(SearchFilter $filter): static;
public function withSort(
    string $field,
    string $direction = 'asc',
): static;
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
use Marko\Database\Connection\ConnectionInterface;
use Marko\Search\Config\SearchConfig;
use Marko\Search\Contracts\SearchableInterface;
use Marko\Search\Driver\DatabaseSearchDriver;

public function __construct(
    private readonly ConnectionInterface $connection,
    private readonly string $tableName,
    private readonly SearchableInterface $searchable,
    private readonly SearchConfig $config,
);
```

Filter fields must be filterable and the sort column sortable, and each row returns only the selectable columns (see [Declaring Filterable, Sortable and Selectable Columns](#declaring-filterable-sortable-and-selectable-columns)). The table name, every searchable, selectable and filter field, and the sort column are quoted through `ConnectionInterface::quoteIdentifier()`, so columns named with reserved words (`key`, `group`, `order`) work on MySQL, MariaDB and PostgreSQL, and a mixed-case PostgreSQL column (`displayName`) is matched exactly instead of being folded to lower case. Each name must still be a plain identifier (letters, digits and underscores, not starting with a digit): anything else, such as `posts.title` or `id; DROP TABLE users`, throws `SearchException` before any SQL runs. The sort direction must be `asc` or `desc`, and every search term and filter value is bound, never interpolated.

The driver uses `LIKE`, so searchable fields must be text columns; PostgreSQL has no `LIKE` on integer columns. Filterable and sortable columns can be any type.

### Exceptions

| Exception | Description |
|-----------|-------------|
| `SearchException` | Base exception for all search errors --- includes `getContext()` and `getSuggestion()` methods. Thrown for an invalid identifier or sort direction, a filter on a column that is not filterable, a sort by a column that is not sortable, and a page below 1 |
