---
title: marko/docs-fts
description: Lightweight lexical documentation search driver backed by SQLite FTS5.
---

Lightweight lexical search driver for Marko documentation, implementing [`DocsSearchInterface`](/docs/packages/docs/) with SQLite FTS5 (BM25 ranking). Zero model, zero external services — it builds a single SQLite file from the [`marko/docs-markdown`](/docs/packages/docs-markdown/) content and queries it. Choose this driver when you want fast keyword search with no machine-learning dependencies; choose [`marko/docs-vec`](/docs/packages/docs-vec/) if you want semantic (vector) ranking.

`docs-fts` and `docs-vec` are sibling drivers of the same `marko/docs` contract — install **one**. (Both binding `DocsSearchInterface` at once is a binding conflict, the same as installing two database drivers.)

## Installation

```bash
composer require marko/docs-fts
```

Requires `ext-pdo_sqlite`.

## Usage

### Build the index

```bash
marko docs-fts:build
```

Reads every page from `marko/docs-markdown` and writes a `docs.sqlite` FTS5 index (a `docs_fts` virtual table with `porter unicode61` tokenizer, plus a `docs_meta` companion table). Rebuilds idempotently.

### Search

`module.php` binds `DocsSearchInterface` to `FtsSearch`, so inject the contract:

```php
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\ValueObject\DocsQuery;

$results = $search->search(new DocsQuery('routing middleware', limit: 10));
```

## API

Implements the full `DocsSearchInterface`: `search()` (BM25-ranked `DocsResult` list with highlighted excerpts), `getPage()`, `listNav()`, and `driverName()` (`fts`). Throws `DocsException` when the SQLite file is missing or the source has zero pages.

## Related Packages

- [`marko/docs`](/docs/packages/docs/) — the search contract
- [`marko/docs-markdown`](/docs/packages/docs-markdown/) — the content it indexes
- [`marko/docs-vec`](/docs/packages/docs-vec/) — the hybrid semantic alternative
