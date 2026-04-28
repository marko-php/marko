# Task 013: Create marko/docs contract package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/docs` contract package containing only interfaces and value objects. Following Marko's interface/driver pattern (mirrors `marko/cache`, `marko/queue`, `marko/session`), this package has no implementation — drivers bind their search engine to the contract.

## Context
- Path: `packages/docs/`
- Namespace: `Marko\Docs\`
- Interfaces: `DocsSearchInterface` (search, getPage, listNav), `DocsDriverInterface`
- Value objects: `DocsQuery`, `DocsResult`, `DocsPage`, `DocsNavEntry`
- No runtime logic; interfaces + readonly value classes only

## Requirements (Test Descriptions)
- [x] `it has composer.json with name marko/docs and PSR-4 namespace Marko\\Docs\\`
- [x] `it defines DocsSearchInterface with search query limit, getPage id, listNav methods`
- [x] `it defines readonly DocsQuery value object with query text and limit`
- [x] `it defines readonly DocsResult value object with pageId title excerpt score`
- [x] `it defines readonly DocsPage value object with id title content path`
- [x] `it defines DocsException with contextual error factories for page-not-found and search-failure`
- [x] `it has no runtime classes beyond value objects and interfaces`

## Acceptance Criteria
- Package installable and autoloadable standalone
- Interfaces covered by type-only tests (signature + docblock assertions)
- Attempting to use contract without a driver throws a loud, helpful error

## Implementation Notes
(Filled in by programmer during implementation)
