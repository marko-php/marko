---
title: Docs Driver Comparison
description: Choose between full-text search (docs-fts) and semantic vector search (docs-vec) for the search_docs MCP tool.
---

The `search_docs` MCP tool in `marko/mcp` is conditional: it is only registered when a `DocsSearchInterface` binding is present in the container. Docs driver packages (`marko/docs-fts`, `marko/docs-vec`, or a custom driver) provide this binding. Without a driver installed, `search_docs` does not appear in the MCP tool list.

Two first-party drivers are available:

- **`docs-fts`** — Full-text search using SQLite FTS5. Fast, zero-dependency, works offline.
- **`docs-vec`** — Semantic vector search using ONNX embeddings. Finds conceptually related results even when exact terms differ.

## Quick comparison

| Feature | docs-fts | docs-vec |
|---|---|---|
| Search type | Lexical (exact terms) | Semantic (meaning-based) |
| Setup | None — works immediately | Requires ONNX model download (~80 MB) |
| Offline | Yes | Yes (after model download) |
| Accuracy on exact queries | Excellent | Good |
| Accuracy on fuzzy/conceptual queries | Limited | Excellent |
| Index build time | Fast (< 1s) | Slower (30–120s depending on corpus size) |
| Index size | Small | Larger (embeddings stored per chunk) |
| PHP extension required | `pdo_sqlite` (standard) | `pdo_sqlite` + FFI |
| Recommended for | Most projects | Projects where agents ask vague or conceptual questions |

## docs-fts (default)

`docs-fts` is the default driver. It uses SQLite FTS5 to index all Markdown documentation — both Marko's own docs and any `resources/ai/guidelines.md` files from installed packages.

**Strengths:**
- Works immediately after `devai:install` with no extra downloads
- Exact term queries return precise results
- Extremely fast even on large corpora

**Limitations:**
- A query for "how do I wire up an event listener" will not match a page titled "Observers" unless it contains those exact words
- No ranking by semantic relevance

## docs-vec (semantic)

`docs-vec` uses an ONNX embedding model to convert documentation chunks into dense vectors. At query time, the agent's question is embedded and compared against stored vectors using cosine similarity.

**Strengths:**
- Finds related content even with different vocabulary
- Ranks results by conceptual closeness, not just keyword overlap
- Works well for vague or exploratory questions

**Limitations:**
- Requires downloading the ONNX model on first use (~80 MB)
- Requires PHP's FFI extension enabled
- Slower index build and slightly higher memory usage
- If the ONNX download fails, see [Troubleshooting](./troubleshooting/)

## Switching drivers

The active driver is set in `config/devai.php`:

```php
return [
    'docs_driver' => env('MARKO_DOCS_DRIVER', 'docs-fts'),
];
```

Switch to semantic search by installing the `marko/docs-vec` package (replace `marko/docs-fts` if you had it). The MCP `search_docs` tool registers automatically against whichever `DocsSearchInterface` driver is bound:

```bash
composer require marko/docs-vec
marko docs-vec:download-model
marko docs-vec:build
```

Lexical search is the equivalent flow with the FTS driver:

```bash
composer require marko/docs-fts
marko docs-fts:build
```

## Choosing a driver

Use **`docs-fts`** when:
- You want zero setup and instant results
- Your agents ask specific, keyword-based questions about the docs
- You are in a restricted network environment where downloading models is difficult

Use **`docs-vec`** when:
- Your agents ask vague or conceptual questions ("how does Marko handle dependencies?")
- You want the agent to find documentation it might miss with exact-term search
- You have FFI available and can spare the one-time model download

## Package READMEs

- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp) — full docs-driver configuration reference
- [`marko/codeindexer`](https://github.com/markshust/marko/tree/develop/packages/codeindexer) — indexer that feeds both drivers
