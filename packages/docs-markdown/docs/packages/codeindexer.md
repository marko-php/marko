---
title: marko/codeindexer
description: Static analysis library that indexes Marko modules into a cached symbol table.
---

Static analysis library that indexes Marko modules — attributes, configs, templates, and translations — into a cached symbol table. `codeindexer` is the shared foundation that powers Marko's AI development tooling: `marko/mcp` (the MCP server) and `marko/lsp` (the language server) both build on its index rather than re-parsing the project themselves.

It walks every module discovered under `vendor/`, `modules/`, and `app/`, parses their PHP attributes (`#[Observer]`, `#[Plugin]`, `#[Preference]`, `#[Command]`, route attributes) plus config/template/translation files, adds the config keys defined in the project-root `config/` directory, and writes the result to a single on-disk cache. The cache is lazy-loaded and auto-rebuilt when stale, so reads are cheap.

## Installation

```bash
composer require marko/codeindexer
```

Most users do not install this directly — it arrives automatically as a dependency of `marko/mcp` or `marko/lsp`. Install it standalone only if you are building your own tooling on top of the index.

## Usage

### Querying the index

Inject the concrete `IndexCache` to use the high-level query API. The cache lazy-loads on first read and re-checks staleness on every subsequent read:

```php
use Marko\CodeIndexer\Cache\IndexCache;

class MyTool
{
    public function __construct(
        private IndexCache $cache,
    ) {}

    public function inspect(): void
    {
        // Inverse lookups
        $observers = $this->cache->findObserversForEvent(UserCreated::class);
        $plugins   = $this->cache->findPluginsForTarget(ProductRepository::class);

        // Full listings
        $modules      = $this->cache->getModules();
        $commands     = $this->cache->getCommands();
        $routes       = $this->cache->getRoutes();
        $configKeys   = $this->cache->getConfigKeys();
        $templates    = $this->cache->getTemplates();
        $translations = $this->cache->getTranslationKeys();
    }
}
```

### Rebuilding the index

The running MCP/LSP server re-checks staleness on **every read** — there is no TTL window. Any addition, edit, or deletion under `app/`, `modules/`, or the project-root `config/`, and any `composer require`, `update`, or `remove`, is visible to tooling on the very next tool call, with no server restart or manual rebuild. You can also force a clean rebuild from the CLI:

```bash
marko indexer:rebuild
```

This writes the cache to `.marko/index.cache` and reports the number of modules, observers, plugins, commands, routes, config keys, templates, and translations indexed.

:::note[What the on-read staleness check covers]
- **`app/`, `modules/`, and the project-root `config/`**: every file is tracked. A file newer than the cache, or a file added or deleted since the last build, marks the index stale.
- **Composer packages in `vendor/`**: tracked through Composer's own state file. The cache records the mtime and size of `vendor/composer/installed.json` (falling back to `composer.lock` when it is missing), which Composer rewrites on every `composer require`, `update`, and `remove`. A change marks the index stale, so new packages appear and removed ones disappear on the next read.

Hand edits to individual files inside `vendor/` are not tracked. Use `marko indexer:rebuild` for those, to force a clean rebuild, or to warm a cold cache (e.g. after a fresh clone or a `devai:install`). It is not needed for routine development or after Composer commands.
:::

Config keys from the project-root `config/` directory are recorded with the module name `project` (`IndexCache::ROOT_CONFIG_MODULE`), so tooling such as `marko-lsp` recognizes app-level keys and overrides.

## API Reference

### `IndexCache`

The concrete indexer. Holds both the raw key/value cache and the high-level symbol queries.

| Method | Returns | Description |
|--------|---------|-------------|
| `build()` | `void` | Scan all modules and write the cache file |
| `load()` | `bool` | Load the cache from disk; `false` if missing/stale |
| `isStale()` | `bool` | Whether a tracked file changed, was added or was removed, or Composer state changed, since the cache was built |
| `getModules()` | `list<ModuleInfo>` | Every discovered module |
| `getObservers()` | `list<ObserverEntry>` | All `#[Observer]` registrations |
| `getPlugins()` | `list<PluginEntry>` | All `#[Plugin]` registrations |
| `getPreferences()` | `list<PreferenceEntry>` | All `#[Preference]` registrations |
| `getCommands()` | `list<CommandEntry>` | All `#[Command]` registrations |
| `getRoutes()` | `list<RouteEntry>` | All route attributes |
| `getConfigKeys()` | `list<ConfigKeyEntry>` | All config keys |
| `getTemplates()` | `list<TemplateEntry>` | All view templates |
| `getTranslationKeys()` | `list<TranslationEntry>` | All translation keys |
| `findObserversForEvent(string $eventClass)` | `list<ObserverEntry>` | Observers listening to a given event |
| `findPluginsForTarget(string $targetClass)` | `list<PluginEntry>` | Plugins targeting a given class |

### `IndexCacheInterface`

The one swappable seam. Defines the raw cache backend (`get`, `set`, `has`, `invalidate`) so the storage mechanism can be replaced via a `#[Preference]` — e.g. an in-memory or Redis-backed cache instead of the default file cache. The symbol-query methods above are concrete to `IndexCache`; type-hint the concrete class when you need them.

### `IndexCacheException`

Thrown when the cache file can't be written or removed. The context includes the operating system's reason, such as `Permission denied`. A corrupt or unreadable cache file isn't an error: `load()` returns `false` and the next read rebuilds the index, without a PHP warning.

| Method | When thrown |
|---|---|
| `IndexCacheException::cacheDirUnwritable($path, $reason)` | `build()` can't create `.marko/` or write `.marko/index.cache` |
| `IndexCacheException::cacheNotRemovable($path, $reason)` | `invalidate()` can't delete an existing `.marko/index.cache` |

## Related Packages

- [`marko/mcp`](/docs/packages/mcp/) — MCP server that exposes the index to AI agents
- [`marko/lsp`](/docs/packages/lsp/) — language server that uses the index for completion, go-to-definition, and diagnostics
