# Task 023: Implement search_docs MCP tool

**Status**: pending
**Depends on**: 022, 013
**Retry count**: 0

> Depends on `marko/docs` (contract) only. Tests use a fake implementation of `DocsSearchInterface`; the actual driver (docs-fts or docs-vec) is bound at runtime by whichever driver the user installs. Binding docs-fts in the test suite would incorrectly couple the MCP package to a specific driver.

## Description
Implement the `search_docs` MCP tool — delegates to `DocsSearchInterface` (bound by whichever docs driver the user installed: docs-fts or docs-vec). Returns ranked results with excerpts suitable for AI context injection.

## Context
- Namespace: `Marko\Mcp\Tools\SearchDocsTool`
- Input schema: `{ query: string, limit?: integer }`
- Output: JSON array of `{ pageId, title, excerpt, url, score }`

## Requirements (Test Descriptions)
- [ ] `it is registered with the MCP server under name search_docs`
- [ ] `it validates input requires query string with optional integer limit`
- [ ] `it delegates to DocsSearchInterface::search`
- [ ] `it returns results formatted as MCP content blocks`
- [ ] `it handles empty result sets gracefully`
- [ ] `it returns an error content block when DocsSearchInterface throws`

## Acceptance Criteria
- Pest tests cover happy path, empty results, and driver failure
- Works with either docs-fts or docs-vec driver installed

## Implementation Notes
(Filled in by programmer during implementation)
