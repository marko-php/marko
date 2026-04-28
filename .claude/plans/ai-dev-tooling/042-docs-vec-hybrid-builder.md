# Task 042: Implement hybrid FTS5 + vector index builder

**Status**: pending
**Depends on**: 041
**Retry count**: 0

## Description
Implement the build-time indexer for docs-vec: reads markdown from `docs-markdown`, generates both an FTS5 table AND a sqlite-vec vec0 table in the same SQLite file. Produces `packages/docs-vec/resources/docs.sqlite` shipped inside the package. Includes a `#[Command(name: 'docs-vec:build')]` CLI entry (`Marko\DocsVec\Commands\BuildIndexCommand`). Package adds `marko/cli` to composer require.

## Context
- Namespace: `Marko\DocsVec\Indexing\HybridIndexBuilder`
- Chunks markdown into semantic chunks (per-heading or sliding-window)
- Embeds each chunk via `VecRuntime` at build time (not runtime)
- Schema: `docs_fts(page_id, chunk_id, title, content)`, `docs_vec(chunk_id, embedding FLOAT[384])`, `docs_meta(page_id, url, section, title)`

## Requirements (Test Descriptions)
- [ ] `it reads all pages from MarkdownRepository`
- [ ] `it chunks markdown into heading-delimited sections`
- [ ] `it writes FTS5 table identical in schema to docs-fts`
- [ ] `it writes sqlite-vec vec0 table with 384-dimensional embeddings per chunk`
- [ ] `it produces queryable BM25 + vector hybrid results`
- [ ] `it is deterministic given identical input and model`
- [ ] `it registers a #[Command(name: 'docs-vec:build')] CLI command`

## Acceptance Criteria
- Build produces valid SQLite file with both tables populated
- File size within acceptable range (sub-50MB for current docs)

## Implementation Notes
(Filled in by programmer during implementation)
