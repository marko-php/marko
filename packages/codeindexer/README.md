# marko/codeindexer

Static analysis library that indexes Marko modules — attributes, configs, templates, translations — into a cached symbol table powering `marko/mcp` and `marko/lsp`.

## Installation

```bash
composer require marko/codeindexer
```

## Quick Example

```php
use Marko\CodeIndexer\Cache\IndexCache;

$cache = $container->get(IndexCache::class);
// Cache is lazy-loaded and auto-rebuilt on first read if missing or stale
$observers = $cache->findObserversForEvent(UserCreated::class);
$plugins   = $cache->findPluginsForTarget(ProductRepository::class);
```

You can also rebuild the index explicitly:

```bash
marko indexer:rebuild
```

## Documentation

Full API reference and configuration: [marko/codeindexer](https://marko.build/docs/ai-assisted-development/codeindexer/)
