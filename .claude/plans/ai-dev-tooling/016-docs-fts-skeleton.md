# Task 016: Create marko/docs-fts package skeleton

**Status**: pending
**Depends on**: 013, 014
**Retry count**: 0

## Description
Create the `marko/docs-fts` driver package skeleton. This is the lightweight lexical-only docs search driver using SQLite FTS5 with BM25 ranking.

## Context
- Path: `packages/docs-fts/`
- Namespace: `Marko\DocsFts\`
- Composer requires: `marko/docs`, `marko/docs-markdown`, `ext-pdo_sqlite`
- Sibling-driver naming: `FtsSearch` implements `DocsSearchInterface`
- No external dependencies beyond SQLite (already in every PHP 8.5 build)

## Requirements (Test Descriptions)
- [x] `it has composer.json with name marko/docs-fts and dependencies on marko/docs and marko/docs-markdown`
- [x] `it declares ext-pdo_sqlite as a required PHP extension`
- [x] `it has module.php binding DocsSearchInterface to FtsSearch`
- [x] `it has src tests/Unit tests/Feature directories with Pest bootstrap`
- [x] `it autoloads cleanly with composer dump-autoload`

## Acceptance Criteria
- Skeleton present, composer autoload works
- Driver class exists as a stub implementing the interface (throws "not implemented" until task 018)

## Implementation Notes
- Created `packages/docs-fts/` with `src/`, `tests/Unit/`, `tests/Feature/` directories
- `FtsSearch` is a stub implementing `DocsSearchInterface`; `search()` throws `searchFailed`, `getPage()` throws `pageNotFound`, `listNav()` returns `[]`
- Added package to root `composer.json` repositories, require, and autoload-dev sections
- Package symlinked via `composer require marko/docs-fts:self.version --ignore-platform-reqs`
- All 5 skeleton tests pass (16 assertions)
