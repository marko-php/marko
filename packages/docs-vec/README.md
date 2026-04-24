# marko/docs-vec

Hybrid FTS5 + sqlite-vec semantic documentation search driver for Marko — combines keyword and vector search for best-in-class relevance.

## Overview

`marko/docs-vec` implements `DocsSearchInterface` using both SQLite FTS5 (keyword) and `sqlite-vec` (vector embeddings) with ONNX Runtime for local inference via `codewithkyrian/transformers-php`. Results are ranked by a weighted combination of BM25 keyword score and cosine similarity, giving accurate answers even when the query wording differs from the documentation. On unsupported platforms or when the model is not downloaded, the package automatically falls back to FTS5-only search. Use `marko/docs-fts` if you prefer a zero-dependency lightweight option.

## Installation

```bash
composer require marko/docs-vec
```

For query-time embeddings, also install the ONNX runtime:

```bash
composer require codewithkyrian/transformers-php
```

## ONNX Model Bundle Requirements

This package uses the **bge-small-en-v1.5** model (~40MB) for generating semantic embeddings. The model is **not** committed to the repository and must be downloaded separately.

### Downloading the Model

```bash
marko docs-vec:download-model
```

This command downloads the bge-small-en-v1.5 ONNX model to `storage/docs-vec/model/`.

### Why Not Bundled?

The ONNX runtime and model files total ~40MB, which is too large for a Composer package. Users who only need FTS5 full-text search (without semantic/vector search) can use the `marko/docs-fts` driver instead.

### Platform Support

The ONNX runtime supports:

| Platform | Architecture |
|----------|-------------|
| Linux    | x64, ARM64  |
| macOS    | x64, ARM64  |
| Windows  | x64         |

### Fallback Behavior

On unsupported platforms, or when the model has not been downloaded, the package automatically falls back to `marko/docs-fts` (FTS5 full-text search only).

## Usage

After installing and downloading the model, bind `DocsSearchInterface` to `VecSearch` in your module configuration (already done automatically via `module.php`).

