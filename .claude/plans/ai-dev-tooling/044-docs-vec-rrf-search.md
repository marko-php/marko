# Task 044: Implement RRF hybrid search driver

**Status**: pending
**Depends on**: 042, 043
**Retry count**: 0

## Description
Implement `VecSearch` — the runtime driver implementing `DocsSearchInterface` via Reciprocal Rank Fusion of FTS5 BM25 + sqlite-vec vector similarity. Produces better recall than either alone.

## Context
- Namespace: `Marko\DocsVec\VecSearch`
- RRF formula: `score = sum(1 / (k + rank_i))` across FTS5 and vector result sets (k typically 60)
- Opens the shipped `resources/docs.sqlite`
- Uses `QueryEmbedder` for vector query construction

## Requirements (Test Descriptions)
- [ ] `it implements DocsSearchInterface`
- [ ] `it runs an FTS5 MATCH query and a vector similarity query in parallel`
- [ ] `it merges ranks via Reciprocal Rank Fusion`
- [ ] `it returns top-N DocsResult objects by combined RRF score`
- [ ] `it generates excerpt snippets from FTS5 highlighting`
- [ ] `it returns empty list for a query with no matches`
- [ ] `it throws DocsException with context when SQLite or model is missing`

## Acceptance Criteria
- Queries complete under 500ms on modern hardware for current docs size
- Recall demonstrably better than docs-fts on a held-out natural-language query set

## Implementation Notes
(Filled in by programmer during implementation)
