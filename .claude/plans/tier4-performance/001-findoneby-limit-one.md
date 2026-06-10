# Task 001: F1 — Add `LIMIT 1` to `findOneBy`

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`Repository::findOneBy` currently delegates to `findBy($criteria)->first()`, which fetches ALL matching rows, hydrates every one, and eager-loads relationships, only to discard all but the first. This backs hot single-row lookups (e.g. `findBySlug`, `findByEmail`). Change `findOneBy` to issue a query capped with `LIMIT 1` so the database returns at most one row, while keeping the single result fully hydrated and eager-loaded.

## Context
- Related files:
  - `packages/database/src/Repository/Repository.php` (`findOneBy` ~232-236, `findBy` for the criteria/column-mapping reference, `eagerLoadRelationships`)
  - `packages/database/tests/Feature/RepositoryCrudTest.php` (inline anonymous `ConnectionInterface` stub recording `['sql','bindings','type']` — the query-count/SQL-assertion pattern to follow)
  - `packages/database/src/Connection/ConnectionInterface.php` (`query(string, array): array`)
- Patterns to follow:
  - Build the WHERE clause with the same property->column mapping `findBy` uses (`metadata->getPropertyToColumnMap()`, criteria joined with ` AND `, positional bindings); append `LIMIT 1` to the SQL. Result-equivalence: because `findBy` issues no `ORDER BY`, the row returned under `LIMIT 1` is the same arbitrary "first" row `findBy()->first()` returned — do NOT introduce an `ORDER BY` (that would change which row is returned). When zero rows match, return `null`.
  - Hydrate `$rows[0]` and eager-load it via the SAME private `eagerLoadRelationships([$entity])` call `find()`/`findBy` use, which reads `$this->pendingRelationships`. The `with(...)->findOneBy(...)` chain must still populate relationships on the single result — verify the clone's `pendingRelationships` flow through the new direct path (do not bypass it by short-circuiting before `eagerLoadRelationships`).
  - Query-count assertions via inline anonymous `ConnectionInterface` stub with `public array &$queries` (mirroring `RepositoryCrudTest`): assert exactly one `query()` call whose SQL ends with/contains `LIMIT 1`, and assert the returned entity equals what the old `findBy()->first()` path produced.

## Requirements (Test Descriptions)
- [ ] `it returns the matching entity for findOneBy when a row exists`
- [ ] `it returns null from findOneBy when no row matches`
- [ ] `it appends LIMIT 1 to the findOneBy query`
- [ ] `it issues exactly one query for findOneBy even when multiple rows would match`
- [ ] `it maps entity property names to column names in the findOneBy WHERE clause`
- [ ] `it eager-loads relationships on the single entity returned by findOneBy`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
