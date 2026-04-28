# Task 011: Implement index cache with invalidation

**Status**: pending
**Depends on**: 006, 007, 008, 009, 010
**Retry count**: 0

## Description
Implement the `IndexCache` service that stitches together ModuleWalker + AttributeParser + ConfigScanner + TemplateScanner + TranslationScanner output into a single serialized cache file at `.marko/index.cache`. Supports rebuild-on-demand and staleness detection via file mtimes.

## Context
- Namespace: `Marko\CodeIndexer\Cache\IndexCache`
- Contract: `Marko\CodeIndexer\Contracts\IndexCacheInterface`
- Storage format: PHP-serialized (opcache-friendly) or JSON (debuggable) — implementer picks, documents choice
- Invalidation: compare cache mtime to walked file mtimes; if any source is newer, mark stale
- Loud error if cache dir unwritable

## Requirements (Test Descriptions)
- [ ] `it builds a fresh index by running all scanners and walkers`
- [ ] `it writes serialized cache to .marko/index.cache`
- [ ] `it loads cache from disk without re-scanning when cache is fresh`
- [ ] `it invalidates cache when any source file mtime exceeds cache mtime`
- [ ] `it exposes getModules, getObservers, getPlugins, getPreferences, getCommands, getRoutes, getConfigKeys, getTemplates, getTranslationKeys methods`
- [ ] `it provides inverse indexes: find observers listening to a given event class, find plugins targeting a given class`
- [ ] `it throws IndexCacheException with helpful suggestion when cache dir is unwritable`

## Acceptance Criteria
- Cache roundtrips exactly the data scanners produce
- Invalidation logic fires on any tracked source change
- Public API covers every consumption need of the MCP + LSP phases

## Implementation Notes
(Filled in by programmer during implementation)
