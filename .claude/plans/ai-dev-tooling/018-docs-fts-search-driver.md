# Task 018: Implement FTS5 search driver

**Status**: pending
**Depends on**: 017
**Retry count**: 0

## Description
Implement `FtsSearch` — the runtime driver implementing `DocsSearchInterface` by querying the pre-built FTS5 SQLite index. Returns ranked `DocsResult` objects.

## Context
- Namespace: `Marko\DocsFts\FtsSearch`
- Uses PDO SQLite to open the shipped `resources/docs.sqlite`
- Uses FTS5 MATCH query with BM25 ranking
- Generates excerpt snippets via FTS5 `snippet()` function

## Requirements (Test Descriptions)
- [ ] `it implements DocsSearchInterface`
- [ ] `it returns ranked DocsResult list for a valid query`
- [ ] `it respects the query limit parameter`
- [ ] `it generates excerpt snippets highlighting query terms`
- [ ] `it returns empty list for a query with no matches`
- [ ] `it throws DocsException with context when SQLite file is missing`
- [ ] `it returns DocsPage via getPage for a valid page id`
- [ ] `it returns nav tree via listNav`

## Acceptance Criteria
- Feature test queries against real docs.sqlite fixture
- Ranks stable across repeated identical queries
- Error paths produce loud, actionable exceptions

## Implementation Notes
(Filled in by programmer during implementation)
