---
title: marko/docs-vec
description: Hybrid FTS5 + sqlite-vec semantic documentation search driver with local ONNX embeddings.
---

Hybrid semantic search driver for Marko documentation, implementing [`DocsSearchInterface`](/docs/packages/docs/). It combines SQLite FTS5 keyword search with `sqlite-vec` vector similarity (local ONNX embeddings via `codewithkyrian/transformers`), ranking results by a weighted blend of BM25 and cosine similarity — so a query finds relevant docs even when the wording differs. When the model isn't present it falls back to FTS5-only keyword search using its own built-in index.

`docs-fts` and `docs-vec` are sibling drivers of the same `marko/docs` contract — install **one**. Choose `docs-vec` for semantic relevance; choose [`marko/docs-fts`](/docs/packages/docs-fts/) for a zero-model lightweight option.

## Installation

```bash
composer require marko/docs-vec
composer require codewithkyrian/transformers   # query-time embeddings (^0.6 with symfony 8)
marko docs-vec:download-extension              # sqlite-vec native binary for this platform
marko docs-vec:download-model                  # bge-small-en-v1.5 ONNX model
marko docs-vec:build                           # build the hybrid index
```

Requires `ext-pdo_sqlite`. Semantic ranking additionally needs the `sqlite-vec` loadable
extension, the ONNX model, `codewithkyrian/transformers`, and a PHP build that permits
loading SQLite extensions.

**Graceful fallback.** `docs-vec` works with `composer require` alone: when the extension,
model, or `transformers` is unavailable (or PHP can't load extensions), both `build` and
`search` automatically degrade to **FTS5-only keyword search** instead of failing. The two
`download-*` commands plus `transformers` upgrade it to full hybrid semantic search.

## The sqlite-vec extension

Vector search relies on the native [`sqlite-vec`](https://github.com/asg017/sqlite-vec)
loadable extension, loaded through PHP's `Pdo\Sqlite::loadExtension()`:

```bash
marko docs-vec:download-extension
```

- Ships pinned, SHA-256-verified `sqlite-vec` builds for macOS, Linux, and Windows (x86_64 / arm64).
- Written to `resources/sqlite-vec/` (gitignored). Mirror override: `--base-url=<your-mirror>`.
- On an unsupported platform, or a PHP build with extension loading disabled, `docs-vec`
  falls back to FTS5-only search.

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
marko docs-vec:download-extension   # once — sqlite-vec native binary
marko docs-vec:download-model       # once — ONNX model
marko docs-vec:build                # build the hybrid index
```

`module.php` binds `DocsSearchInterface` to `VecSearch` automatically; inject the contract and call `search(new DocsQuery(...))`.

## API

Implements the full `DocsSearchInterface` (`search`, `getPage`, `listNav`, `driverName` → `vec`). `search()` runs FTS5 to gather candidates, embeds the query, and re-ranks by combined BM25 + cosine score.

## Related Packages

- [`marko/docs`](/docs/packages/docs/) — the search contract
- [`marko/docs-markdown`](/docs/packages/docs-markdown/) — the content it indexes
- [`marko/docs-fts`](/docs/packages/docs-fts/) — the lightweight keyword-only alternative
