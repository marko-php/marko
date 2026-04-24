# Task 040: Create marko/docs-vec package skeleton

**Status**: pending
**Depends on**: 013, 014
**Retry count**: 0

## Description
Create the `marko/docs-vec` driver package skeleton. This is the hybrid FTS5 + sqlite-vec semantic search driver with bundled ONNX model for query-time embeddings.

## Context
- Path: `packages/docs-vec/`
- Namespace: `Marko\DocsVec\`
- Composer requires: `marko/docs`, `marko/docs-markdown`, `codewithkyrian/transformers-php`, `sqlite-vec/sqlite-vec`
- Requires `ext-pdo_sqlite` and ability to load sqlite-vec extension

## Requirements (Test Descriptions)
- [ ] `it has composer.json with name marko/docs-vec and required dependencies`
- [ ] `it has module.php binding DocsSearchInterface to VecSearch`
- [ ] `it has src tests/Unit tests/Feature directories with Pest bootstrap`
- [ ] `it autoloads cleanly with composer dump-autoload`
- [ ] `it documents ONNX model bundle requirements in README placeholder`

## Acceptance Criteria
- Skeleton present, composer autoload works
- Driver class exists as a stub implementing the interface

## Implementation Notes
(Filled in by programmer during implementation)
