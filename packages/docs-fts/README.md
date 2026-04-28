# marko/docs-fts

FTS5 lexical documentation search driver for Marko — fast, dependency-free full-text search over Marko docs.

## Overview

`marko/docs-fts` implements `DocsSearchInterface` using SQLite's built-in FTS5 engine. It indexes the canonical documentation from `marko/docs-markdown` into a local SQLite database and answers queries with ranked keyword results. No external services, no model downloads — just SQLite. Use this driver when you want fast, lightweight search without semantic ranking.

## Installation

```bash
composer require marko/docs-fts
```

## Usage

Build the search index (run once, re-run after updating `marko/docs-markdown`):

```bash
marko docs-fts:build
```

The index is written to `.marko/docs-fts.sqlite`. Once built, search is available automatically through the `DocsSearchInterface` binding and via `marko/mcp`'s `search_docs` tool.

```php
use Marko\Docs\Contract\DocsSearchInterface;

$results = $container->get(DocsSearchInterface::class)->search('rate limiting');
```

## API Reference

- `FtsSearch::search(string $query, int $limit = 20)` — BM25-ranked keyword search
- `marko docs-fts:build` — Build or rebuild the FTS5 index

## Documentation

Full configuration and usage: [marko/docs-fts](https://marko.build/docs/packages/docs-fts/)
