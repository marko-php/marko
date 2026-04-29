---
title: Docs Driver Comparison
description: Choose between full-text search (docs-fts) and semantic vector search (docs-vec) for the search_docs MCP tool.
---

The `search_docs` MCP tool is conditional — it only appears when a `DocsSearchInterface` is bound in the container. Two first-party drivers provide that binding:

- **`marko/docs-fts`** — Full-text search using SQLite FTS5. Fast, zero-setup, no extra dependencies.
- **`marko/docs-vec`** — Semantic vector search using a small ONNX embedding model. Finds conceptually related content even when exact terms differ.

Neither is bundled with `marko/devai`. You install whichever you want explicitly. After `marko devai:install`, the installer prints a hint reminding you of the two choices if no driver is detected.

## TL;DR — which one?

Pick **`marko/docs-fts`** unless you specifically know you want semantic search. It works on every PHP install with SQLite (i.e. all of them), needs no model download, and answers most "find me the page about X" questions correctly. Ship `docs-vec` only if your agents ask vague conceptual questions where keywords don't overlap with documentation wording.

## Quick comparison

| Feature | docs-fts | docs-vec |
|---|---|---|
| Search type | Lexical (exact terms) | Semantic (meaning-based) |
| Setup | None — works immediately | Requires ONNX model download (~40 MB) |
| Offline | Yes | Yes (after model download) |
| Exact-query accuracy | Excellent | Good |
| Conceptual-query accuracy | Limited | Excellent |
| Index build time | Fast (< 1s) | Slower (30–120s depending on corpus size) |
| Index size | Small | Larger (embeddings per chunk) |
| Required PHP extensions | `pdo_sqlite` (standard) | `pdo_sqlite` + `sqlite-vec` |

## Install fts (recommended)

```bash
composer require --dev marko/docs-fts
marko docs-fts:build
```

The build step indexes Marko's docs and any `resources/ai/guidelines.md` files from installed packages. Re-run `marko docs-fts:build` after adding new packages.

## Install vec (semantic)

```bash
composer require --dev marko/docs-vec
marko docs-vec:download-model
marko docs-vec:build
```

`docs-vec` requires the [`sqlite-vec`](https://github.com/asg017/sqlite-vec) extension. If the ONNX download fails or `sqlite-vec` isn't loaded, see [Troubleshooting](./troubleshooting/).

## Switching drivers

Marko intentionally avoids silent precedence — having both drivers installed and "letting one win" is exactly the kind of magic the framework forbids. To swap:

```bash
composer remove --dev marko/docs-fts
composer require --dev marko/docs-vec
marko docs-vec:download-model
marko docs-vec:build
```

Or in reverse. Your `composer.json` should list exactly the driver you want.

## Choosing fts

Use **`docs-fts`** when:
- You want zero setup, no model downloads, no extra extensions
- Your agents ask specific, keyword-based questions about the docs
- You're in a restricted network environment where model downloads are awkward

## Choosing vec

Use **`docs-vec`** when:
- Your agents ask vague or conceptual questions ("how does Marko handle dependencies?")
- You want results ranked by meaning, not term overlap
- You can install `sqlite-vec` and spare the one-time ~40 MB model download

## Package READMEs

- [`marko/docs`](https://github.com/markshust/marko/tree/develop/packages/docs) — the contract package both drivers implement
- [`marko/docs-fts`](https://github.com/markshust/marko/tree/develop/packages/docs-fts)
- [`marko/docs-vec`](https://github.com/markshust/marko/tree/develop/packages/docs-vec)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp) — registers `search_docs` against whichever driver is bound
