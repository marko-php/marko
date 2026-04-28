# Task 017: Implement FTS5 index builder for docs-fts

**Status**: pending
**Depends on**: 016
**Retry count**: 0

## Description
Implement the build-time indexer that reads markdown from `marko/docs-markdown` and produces a `docs.sqlite` file containing an FTS5 virtual table with BM25 ranking. The resulting SQLite file ships inside the `marko/docs-fts` package and is queried at runtime by the driver.

## Context
- Namespace: `Marko\DocsFts\Indexing\FtsIndexBuilder`
- Output: `packages/docs-fts/resources/docs.sqlite`
- Schema: `docs_fts(page_id, title, content, tokenize='porter unicode61')`, `docs_meta(page_id, url, section, title, last_updated)`
- Build invoked via CLI: `marko docs-fts:build`. This task MUST include the `#[Command(name: 'docs-fts:build')]` class (`Marko\DocsFts\Commands\BuildIndexCommand`) so the build is reproducible from the CLI and from CI. Package adds `marko/cli` to composer require.

## Requirements (Test Descriptions)
- [ ] `it reads all pages from MarkdownRepository`
- [ ] `it writes FTS5 virtual table docs_fts with porter unicode61 tokenizer`
- [ ] `it writes companion docs_meta table with page metadata`
- [ ] `it produces queryable BM25-ranked results`
- [ ] `it ranks exact title matches higher than body-only matches`
- [ ] `it overwrites existing docs.sqlite on rebuild idempotently`
- [ ] `it throws DocsException when MarkdownRepository returns zero pages`
- [ ] `it registers a #[Command(name: 'docs-fts:build')] CLI command that invokes the builder and reports output path`

## Acceptance Criteria
- Build produces valid SQLite file
- File is under a reasonable size (sub-10MB for current docs)
- Rebuild is deterministic given identical input

## Implementation Notes
(Filled in by programmer during implementation)
