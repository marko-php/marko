# marko/search

Generic search abstraction--add full-text search to any entity with a database driver included and support for Elasticsearch, Meilisearch, and Typesense drivers.

## Installation

```bash
composer require marko/search
```

## Quick Example

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
    criteria: SearchCriteria::create('php tutorial')
        ->withSort('created_at', 'desc')
        ->withPage(1)
        ->withPerPage(10),
);
```

## Documentation

Full usage, API reference, and examples: [marko/search](https://marko.build/docs/packages/search/)
