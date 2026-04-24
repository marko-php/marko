# Task 041: Integrate sqlite-vec extension + bundle ONNX embedding model

**Status**: pending
**Depends on**: 040
**Retry count**: 0

## Description
Wire up the `sqlite-vec` loadable extension at PDO connection open time and bundle the bge-small-en-v1.5 ONNX model (~40MB) under `packages/docs-vec/resources/models/`. Provide a service that initializes both for use by the index builder and search driver.

## Context
- Namespace: `Marko\DocsVec\Runtime\VecRuntime`
- Loads sqlite-vec via `$pdo->sqliteCreateFunction()` or `$pdo->loadExtension()` (preferred if available)
- Model: bge-small-en-v1.5 (384-dim embeddings)
- Model files **NOT committed to git**. Fetched by a post-install downloader script (`bin/download-model.php` invoked via composer `post-install-cmd` / `post-update-cmd` when present, or via `marko docs-vec:download-model` CLI). Cached to `resources/models/bge-small-en-v1.5/` on first run; subsequent runs skip. Downloader verifies SHA-256 checksum of each file and fails loudly on mismatch. Build step (task 042) invokes the downloader first if model is missing.
- Uses `transformers-php` for inference
- `.gitignore` inside `resources/models/` excludes the model weights but preserves the directory structure

## Requirements (Test Descriptions)
- [ ] `it opens an in-memory SQLite connection with sqlite-vec loaded`
- [ ] `it registers the vec0 virtual table type successfully`
- [ ] `it loads the bundled bge-small-en-v1.5 ONNX model from resources`
- [ ] `it embeds a query string into a 384-dimensional vector`
- [ ] `it produces stable embeddings for identical input`
- [ ] `it throws VecRuntimeException with helpful context when sqlite-vec cannot be loaded`
- [ ] `it throws VecRuntimeException with helpful context when ONNX model is missing, suggesting marko docs-vec:download-model`
- [ ] `it provides a download-model CLI command (#[Command(name: 'docs-vec:download-model')]) that fetches model weights from the upstream source and verifies SHA-256 checksums`
- [ ] `it skips download when model files already exist and checksums match`
- [ ] `it fails loudly when checksum verification fails`

## Acceptance Criteria
- Tests run on CI environments with standard PHP 8.5 + transformers-php install; tests that require the sqlite-vec loadable extension or the 40MB ONNX model skip cleanly (via Pest `skip()` with reason) when the extension/model is unavailable so CI stays green on base images that lack them. Provide a CI job or documented setup step that installs sqlite-vec and downloads the model on demand.
- Failure modes produce actionable errors

## Implementation Notes
(Filled in by programmer during implementation)
