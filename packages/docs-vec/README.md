# marko/docs-vec

Hybrid FTS5 + sqlite-vec semantic documentation search driver for Marko — combines keyword and vector search for best-in-class relevance.

## Overview

`marko/docs-vec` implements `DocsSearchInterface` using both SQLite FTS5 (keyword) and `sqlite-vec` (vector embeddings) with ONNX Runtime for local inference via `codewithkyrian/transformers`. Results are fused (reciprocal-rank fusion) across BM25 keyword ranking and cosine similarity, giving accurate answers even when the query wording differs from the documentation. When the `sqlite-vec` extension, the model, or `transformers` is unavailable — or the PHP build can't load SQLite extensions — it falls back to FTS5-only keyword search using its own built-in index. Use `marko/docs-fts` instead if you only want lightweight keyword search.

## Installation

```bash
composer require marko/docs-vec
```

That alone gives you working FTS5-only search. For full hybrid semantic search, add the
ONNX runtime and fetch the native extension and model:

```bash
composer require codewithkyrian/transformers   # ^0.6 with symfony 8 (^0.5 with symfony 6/7)
marko docs-vec:download-extension              # sqlite-vec native binary for this platform
marko docs-vec:download-model                  # bge-small-en-v1.5 ONNX model
marko docs-vec:build                           # build the hybrid index
```

> **Graceful fallback.** Every piece above is optional: if the extension, model, or
> `transformers` is missing (or PHP can't load SQLite extensions), `build` and `search`
> degrade to FTS5-only instead of failing. `marko docs-vec:build` reports which mode it used.

## The sqlite-vec extension

Vector search loads the native [`sqlite-vec`](https://github.com/asg017/sqlite-vec) extension
via PHP's `Pdo\Sqlite::loadExtension()` (not a `SELECT load_extension(...)` SQL call, which
SQLite blocks by default). Fetch a pinned, checksum-verified build for your platform:

```bash
marko docs-vec:download-extension
```

Ships `sqlite-vec` builds for macOS, Linux, and Windows (x86_64 / arm64), written to
`resources/sqlite-vec/` (gitignored). Mirror/firewall override: `--base-url=<your-mirror>`.

## ONNX model

This package uses the **bge-small-en-v1.5** model (~130MB across `onnx/model.onnx` plus `config.json`, `tokenizer.json`, `tokenizer_config.json`, and `special_tokens_map.json`) for semantic embeddings. The files use the HuggingFace layout transformers-php expects. The model is **not** committed to the repository — it is downloaded on demand and verified by SHA-256.

### Downloading the model

```bash
marko docs-vec:download-model
```

Files are written into the package at `resources/models/bge-small-en-v1.5/` (gitignored). The download is pinned to a specific HuggingFace commit and each file's SHA-256 is verified. Behind a firewall or using a mirror? Pass `--base-url=<your-mirror>`:

```bash
marko docs-vec:download-model --base-url=https://my-mirror.example.com/bge-small-en-v1.5
```

`marko docs-vec:build` fails loudly with a pointer to this command if the model is missing.

### Why not bundled?

The model is ~130MB — too large to commit to a Composer package. If you only need keyword search (no semantic/vector ranking), use the lighter `marko/docs-fts` driver instead, which needs no model.

### Platform support

The ONNX runtime supports Linux (x64, ARM64), macOS (x64, ARM64), and Windows (x64). On unsupported platforms, or when the model has not been downloaded, `docs-vec` falls back to FTS5-only keyword search (no semantic ranking) using its own built-in index — it does not depend on the `marko/docs-fts` package.

## Usage

`module.php` binds `DocsSearchInterface` to `VecSearch` automatically. After fetching the
extension and model, build the index, then inject the contract and search:

```bash
marko docs-vec:download-extension
marko docs-vec:download-model
marko docs-vec:build
```

## Documentation

Full configuration, ranking details, and the docs-driver comparison: [marko/docs-vec](https://marko.build/docs/packages/docs-vec/)
