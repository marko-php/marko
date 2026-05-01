---
title: Docs Driver Comparison
description: How marko/devai bundles docs-fts by default and how to upgrade to docs-vec for semantic search.
---

The `search_docs` MCP tool is backed by a `DocsSearchInterface` binding. Two first-party drivers implement it:

- **`marko/docs-fts`** — Full-text search using SQLite FTS5. Fast, zero-setup, no extra dependencies.
- **`marko/docs-vec`** — Semantic vector search using a small ONNX embedding model. Finds conceptually related content even when exact terms differ.

`marko/devai` hard-requires `marko/docs-fts`, so search_docs works out of the box on every fresh install — no extra steps. The orchestrator runs `marko docs-fts:build` automatically as part of `devai:install`, so the index is ready by the time the install command returns.

## Default — fts, no choice required

When you run `composer require --dev marko/devai`, Composer pulls in `marko/docs-fts` as a transitive dep. When you then run `marko devai:install`, the installer:

1. Writes per-agent configs (CLAUDE.md, AGENTS.md, etc.)
2. Registers the MCP and LSP servers
3. Distributes skills
4. **Builds the docs search index** — `marko docs-fts:build`

After that, `search_docs` shows up in the marko-mcp tool list and returns real results immediately.

## Upgrade to vec (semantic)

If you want semantic search instead of lexical, install `marko/docs-vec`. It declares Composer `replace` for `marko/docs-fts`, so a single `composer require` swaps the driver:

```bash
composer require --dev marko/docs-vec
marko docs-vec:download-model
marko docs-vec:build
```

Composer removes `marko/docs-fts` and installs `marko/docs-vec` in one operation. No silent precedence — you have exactly one driver installed at any time. Re-running `marko devai:install` will then build the vec index instead of fts (the orchestrator detects which driver is in vendor/).

## Switching back to fts

```bash
composer remove --dev marko/docs-vec
composer require --dev marko/docs-fts
marko docs-fts:build
```

Or just re-run `marko devai:install --force` after the composer commands and the build runs automatically.

## Quick comparison

| Feature | docs-fts | docs-vec |
|---|---|---|
| Search type | Lexical (exact terms) | Semantic (meaning-based) |
| Setup | None — bundled with devai | Requires ONNX model download (~40 MB) |
| Offline | Yes | Yes (after model download) |
| Exact-query accuracy | Excellent | Good |
| Conceptual-query accuracy | Limited | Excellent |
| Index build time | Fast (< 1s) | Slower (30–120s depending on corpus size) |
| Index size | Small | Larger (embeddings per chunk) |
| Required PHP extensions | `pdo_sqlite` (standard) | `pdo_sqlite` + `sqlite-vec` |

## Choosing fts

Use **`docs-fts`** (the default) when:
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
- [`marko/docs-fts`](https://github.com/markshust/marko/tree/develop/packages/docs-fts) — bundled default
- [`marko/docs-vec`](https://github.com/markshust/marko/tree/develop/packages/docs-vec) — semantic upgrade (replaces fts)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp) — registers `search_docs` against whichever driver is bound
