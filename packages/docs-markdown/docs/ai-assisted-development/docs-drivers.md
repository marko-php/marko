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
composer require --dev codewithkyrian/transformers   # query-time embeddings (^0.6 with symfony 8)
marko docs-vec:download-extension                     # sqlite-vec native binary for this platform
marko docs-vec:download-model                         # bge-small-en-v1.5 ONNX model
marko docs-vec:build                                  # build the hybrid FTS5 + vector index
```

Re-running `marko devai:install` then builds the vec index — the orchestrator detects which driver is in `vendor/` (preferring `docs-vec` when present) and builds that one.

**Graceful fallback.** Semantic ranking needs three things: the `sqlite-vec` extension, the ONNX model, and `codewithkyrian/transformers`. If any is missing — or the PHP build can't load SQLite extensions — `docs-vec` automatically builds and serves an **FTS5-only** index instead of failing, and `marko docs-vec:build` reports which mode it used. So `composer require marko/docs-vec` alone already works lexically; the two `download-*` commands plus `transformers` upgrade it to full semantic search.

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
| Search type | Lexical (exact terms) | Hybrid: FTS5 keyword + semantic vector (meaning-based) |
| Setup | None — single `composer require` | `download-extension` + `download-model` (~130 MB) + `composer require codewithkyrian/transformers` |
| Offline | Yes | Yes (after the one-time downloads) |
| Exact-query accuracy | Excellent | Excellent (FTS5 half) |
| Conceptual-query accuracy | Limited | Excellent |
| Index build time | Fast (< 1s) | Slower (~30–120s — one ONNX embedding per chunk) |
| Index size | Small | Larger (a 384-d embedding per chunk) |
| Required PHP extensions | `pdo_sqlite` (standard) | `pdo_sqlite` + the `sqlite-vec` loadable extension; PHP must permit extension loading (falls back to FTS5 otherwise) |

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
- You can spare the one-time downloads (`marko docs-vec:download-extension` fetches the platform `sqlite-vec` binary; `marko docs-vec:download-model` fetches the ~130 MB ONNX model) and your PHP build permits loading SQLite extensions

`marko docs-vec:download-extension` ships pinned, checksum-verified `sqlite-vec` builds for macOS, Linux, and Windows (x86_64 / arm64). On an unsupported platform — or a PHP build with extension loading compiled out — `docs-vec` degrades to FTS5-only rather than failing.

## Package READMEs

- [`marko/docs`](https://github.com/markshust/marko/tree/develop/packages/docs) — the contract package both drivers implement
- [`marko/docs-fts`](https://github.com/markshust/marko/tree/develop/packages/docs-fts) — recommended default driver
- [`marko/docs-vec`](https://github.com/markshust/marko/tree/develop/packages/docs-vec) — semantic alternative
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp) — registers `search_docs` against whichever driver is bound
