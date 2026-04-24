# marko/codeindexer

Static analysis library that indexes Marko modules — attributes, configs, templates, translations — into a cached symbol table powering `marko/mcp` and `marko/lsp`.

## Overview

The codeindexer walks every installed module and builds a structured index of all framework metadata: observers, plugins, preferences, commands, routes, config keys, templates, and translations. This index is consumed by `marko/mcp` to answer AI agent queries and by `marko/lsp` to power editor completions and diagnostics. The index is written to `.marko/index.cache` and reloaded on subsequent requests when fresh.

## Installation

```bash
composer require marko/codeindexer
```

## Usage

```bash
marko indexer:rebuild
```

Builds `.marko/index.cache` from all installed modules. Re-run after any code change that affects framework metadata.

```php
$cache = $container->get(IndexCache::class);
$observers = $cache->findObserversForEvent(UserCreated::class);
$plugins   = $cache->findPluginsForTarget(ProductRepository::class);
```

## API Reference

- `IndexCache::build()` — Run all scanners and persist to disk
- `IndexCache::load()` — Load cached index from disk (returns false if stale)
- `IndexCache::getModules()` — All registered modules
- `IndexCache::getObservers()` — All observer definitions
- `IndexCache::getPlugins()` — All plugin definitions
- `IndexCache::getPreferences()` — All preference overrides
- `IndexCache::getCommands()` — All CLI commands
- `IndexCache::getRoutes()` — All routes
- `IndexCache::getConfigKeys()` — All declared config keys
- `IndexCache::getTemplates()` — All template paths
- `IndexCache::getTranslationKeys()` — All translation keys
- `IndexCache::findObserversForEvent(string $eventClass)` — Inverse query by event
- `IndexCache::findPluginsForTarget(string $targetClass)` — Inverse query by target

## Documentation

Full usage and configuration: [marko/codeindexer](https://marko.build/docs/ai-assisted-development/codeindexer/)
