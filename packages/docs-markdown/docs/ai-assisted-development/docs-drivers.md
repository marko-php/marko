---
title: Docs Driver Comparison
description: How marko/devai picks up a docs search driver, and how to choose between docs-fts and docs-vec.
---

The `search_docs` MCP tool is backed by a `DocsSearchInterface` binding. Two first-party drivers implement it:

- **`marko/docs-fts`** — Full-text search using SQLite FTS5. Fast, zero-setup, no extra dependencies.
- **`marko/docs-vec`** — Semantic vector search using a small ONNX embedding model. Finds conceptually related content even when exact terms differ.

`marko/devai` requires only `marko/docs` — the contract package — not a specific driver. You choose which driver to install. `marko/docs-fts` and `marko/docs-vec` are independent siblings that both bind `DocsSearchInterface`, so **install exactly one**: binding two implementations of the same interface raises a loud binding-conflict error at boot.

A fresh `marko/skeleton` project lists `marko/docs-fts` in its `suggest` block as the recommended default, so the out-of-the-box path is a single `composer require`.

## Recommended — docs-fts

```bash
composer require --dev marko/docs-fts
marko devai:install
```

When you run `marko devai:install`, the installer:

1. Writes per-agent configs (CLAUDE.md, AGENTS.md, etc.)
2. Registers the MCP and LSP servers
3. Distributes skills
4. **Builds the docs search index** for whichever driver is installed — here, `marko docs-fts:build`

After that, `search_docs` shows up in the marko-mcp tool list and returns real results immediately.

If you run `marko devai:install` with **no** docs driver installed, the install still completes — it just logs a note telling you to `composer require marko/docs-fts` and re-run the build. `search_docs` simply won't appear until a driver is bound.

## Using docs-vec (semantic)

If you want semantic search instead of lexical, install `marko/docs-vec` *instead of* `marko/docs-fts`:

```bash
composer require --dev marko/docs-vec
marko docs-vec:download-model
marko docs-vec:build
```

Re-running `marko devai:install` then builds the vec index — the orchestrator detects which driver is in `vendor/` (preferring `docs-vec` when present) and builds that one.

## Switching drivers

Because there is no Composer `replace` between them, switch by removing one and adding the other so exactly one remains bound:

```bash
# fts -> vec
composer remove --dev marko/docs-fts
composer require --dev marko/docs-vec

# vec -> fts
composer remove --dev marko/docs-vec
composer require --dev marko/docs-fts
```

Then re-run `marko devai:install --force` (or the driver's `:build` command) to rebuild the index.

## Quick comparison

| Feature | docs-fts | docs-vec |
|---|---|---|
| Search type | Lexical (exact terms) | Semantic (meaning-based) |
| Setup | None — single `composer require` | Requires ONNX model download (~40 MB) |
| Offline | Yes | Yes (after model download) |
| Exact-query accuracy | Excellent | Good |
| Conceptual-query accuracy | Limited | Excellent |
| Index build time | Fast (< 1s) | Slower (30–120s depending on corpus size) |
| Index size | Small | Larger (embeddings per chunk) |
| Required PHP extensions | `pdo_sqlite` (standard) | `pdo_sqlite` + `sqlite-vec` |

## Choosing fts

Use **`docs-fts`** (the recommended default) when:
- You want zero setup, no model downloads, no extra extensions
- Your agents ask specific, keyword-based questions about the docs
- You're in a restricted network environment where model downloads are awkward

Most projects should stay on fts.

## Choosing vec

Use **`docs-vec`** when:
- Your agents ask vague or conceptual questions ("how does Marko handle dependencies?")
- You want results ranked by meaning, not term overlap
- You can install `sqlite-vec` and spare the one-time ~40 MB model download

## Package READMEs

- [`marko/docs`](https://github.com/markshust/marko/tree/develop/packages/docs) — the contract package both drivers implement
- [`marko/docs-fts`](https://github.com/markshust/marko/tree/develop/packages/docs-fts) — recommended default driver
- [`marko/docs-vec`](https://github.com/markshust/marko/tree/develop/packages/docs-vec) — semantic alternative
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp) — registers `search_docs` against whichever driver is bound
