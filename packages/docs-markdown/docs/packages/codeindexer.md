---
title: marko/codeindexer
description: Static analysis library that indexes Marko modules into a cached symbol table.
---

Static analysis library that indexes Marko modules — attributes, configs, templates, and translations — into a cached symbol table. `codeindexer` is the shared foundation that powers Marko's AI development tooling: `marko/mcp` (the MCP server) and `marko/lsp` (the language server) both build on its index rather than re-parsing the project themselves.

It walks every module discovered under `vendor/`, `modules/`, and `app/`, parses their PHP attributes (`#[Observer]`, `#[Plugin]`, `#[Preference]`, `#[Command]`, route attributes) plus config/template/translation files, and writes the result to a single on-disk cache. The cache is lazy-loaded and auto-rebuilt when stale, so reads are cheap.

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

The running MCP/LSP server re-checks staleness on **every read** — there is no TTL window. Any addition, edit, or deletion under `app/` or `modules/` is visible to tooling on the very next tool call, with no server restart or manual rebuild. You can also force a clean rebuild from the CLI:

```bash
marko indexer:rebuild
```

This writes the cache to `.marko/index.cache` and reports the number of modules, observers, plugins, commands, routes, config keys, templates, and translations indexed.

:::note[On-read self-refresh covers `app/` and `modules/`; vendor needs an explicit rebuild]
The running MCP/LSP server re-checks staleness on every read — there is no TTL window. Additions, edits, and deletions under `app/` and `modules/` are picked up automatically; the very next tool call sees the updated index. **Vendor is excluded** from the on-read check: new or removed packages installed via `composer require` / `composer update` are not detected until you run `marko indexer:rebuild`. Use `marko indexer:rebuild` for three scenarios: a forced clean rebuild, warming a cold cache (e.g. after a fresh clone or a `devai:install`), or picking up vendor changes. It is not needed for routine development under `app/` or `modules/`.
:::

## API Reference

### `IndexCache`

The concrete indexer. Holds both the raw key/value cache and the high-level symbol queries.

| Method | Returns | Description |
|--------|---------|-------------|
| `build()` | `void` | Scan all modules and write the cache file |
| `load()` | `bool` | Load the cache from disk; `false` if missing/stale |
| `isStale()` | `bool` | Whether any source file is newer than the cache |
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

## Related Packages

- [`marko/mcp`](/docs/packages/mcp/) — MCP server that exposes the index to AI agents
- [`marko/lsp`](/docs/packages/lsp/) — language server that uses the index for completion, go-to-definition, and diagnostics
