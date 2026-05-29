---
title: marko/docs-vec
description: Hybrid FTS5 + sqlite-vec semantic documentation search driver with local ONNX embeddings.
---

Hybrid semantic search driver for Marko documentation, implementing [`DocsSearchInterface`](/docs/packages/docs/). It combines SQLite FTS5 keyword search with `sqlite-vec` vector similarity (local ONNX embeddings via `codewithkyrian/transformers-php`), ranking results by a weighted blend of BM25 and cosine similarity — so a query finds relevant docs even when the wording differs. When the model isn't present it falls back to FTS5-only keyword search using its own built-in index.

`docs-fts` and `docs-vec` are sibling drivers of the same `marko/docs` contract — install **one**. Choose `docs-vec` for semantic relevance; choose [`marko/docs-fts`](/docs/packages/docs-fts/) for a zero-model lightweight option.

## Installation

```bash
composer require marko/docs-vec
composer require codewithkyrian/transformers-php   # query-time embeddings
```

Requires `ext-pdo_sqlite`. Vector search additionally needs the `sqlite-vec` extension.

## The ONNX model

`docs-vec` uses **bge-small-en-v1.5** (~130MB) for embeddings. It is **not** committed — download it on demand:

```bash
marko docs-vec:download-model
```

- Pinned to a specific HuggingFace commit; every file is SHA-256 verified.
- Written to `resources/models/bge-small-en-v1.5/` (gitignored).
- Mirror/firewall override: `--base-url=<your-mirror>`.
- `marko docs-vec:build` fails loudly pointing here if the model is missing.

## Usage

```bash
marko docs-vec:download-model   # once
marko docs-vec:build            # build the hybrid index
```

`module.php` binds `DocsSearchInterface` to `VecSearch` automatically; inject the contract and call `search(new DocsQuery(...))`.

## API

Implements the full `DocsSearchInterface` (`search`, `getPage`, `listNav`, `driverName` → `vec`). `search()` runs FTS5 to gather candidates, embeds the query, and re-ranks by combined BM25 + cosine score.

## Related Packages

- [`marko/docs`](/docs/packages/docs/) — the search contract
- [`marko/docs-markdown`](/docs/packages/docs-markdown/) — the content it indexes
- [`marko/docs-fts`](/docs/packages/docs-fts/) — the lightweight keyword-only alternative
