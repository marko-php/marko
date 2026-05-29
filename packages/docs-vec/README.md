# marko/docs-vec

Hybrid FTS5 + sqlite-vec semantic documentation search driver for Marko — combines keyword and vector search for best-in-class relevance.

## Overview

`marko/docs-vec` implements `DocsSearchInterface` using both SQLite FTS5 (keyword) and `sqlite-vec` (vector embeddings) with ONNX Runtime for local inference via `codewithkyrian/transformers-php`. Results are ranked by a weighted combination of BM25 keyword score and cosine similarity, giving accurate answers even when the query wording differs from the documentation. When the model is not downloaded (or on a platform without ONNX support), it falls back to FTS5-only keyword search using its own built-in index. Use `marko/docs-fts` instead if you only want lightweight keyword search.

## Installation

```bash
composer require marko/docs-vec
```

For query-time embeddings, also install the ONNX runtime:

```bash
composer require codewithkyrian/transformers-php
```

## ONNX model

This package uses the **bge-small-en-v1.5** model (~130MB across `model.onnx`, `tokenizer.json`, `config.json`) for semantic embeddings. The model is **not** committed to the repository — it is downloaded on demand and verified by SHA-256.

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

After installing and downloading the model, `module.php` binds `DocsSearchInterface` to `VecSearch` automatically. Build the hybrid index, then search:

```bash
marko docs-vec:build
```

## Documentation

Full configuration, ranking details, and the docs-driver comparison: [marko/docs-vec](https://marko.build/docs/packages/docs-vec/)
