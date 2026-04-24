# Plan: Database Layer 1.0 Gaps

## Created
2026-04-24

## Status
completed

## Objective
Close the remaining architectural gaps in the Marko database layer before tagging 1.0: UUID/string primary key support, loud PK validation, aggregate functions, GROUP BY/HAVING, DISTINCT/UNION, column aliasing, bulk insert, JSON column type, and consistent spec+eager-load composition.

## Related Issues
none

## Scope

### In Scope
- Widen `Repository::find(int $id)` to `find(int|string $id)`; update `RelationshipLoader` and hydration/dirty-tracking/save paths to handle string PKs (UUIDs).
- Remove silent `'id'` fallbacks in `Repository`; throw at metadata-parse time when no primary key is declared.
- Add `min()`, `max()`, `sum()`, `avg()`, `count()` aggregate methods to `QueryBuilderInterface` (explicit methods, no `__call`).
- Add `groupBy(string ...$columns)` and `having(...)` to `QueryBuilderInterface`.
- Add `distinct()`, `union(QueryBuilderInterface)`, `unionAll(QueryBuilderInterface)` to the query builder.
- Support column aliasing in `select('users.name as author_name')` and aggregate expressions like `COUNT(*) as total`, with identifier-only alias whitelist to prevent injection.
- Add `insertBatch(array $entities)` to `RepositoryInterface` — single multi-row INSERT, lifecycle events per entity, relationships NOT auto-persisted (documented escape hatch).
- Support `#[Column(type: 'json')]` with automatic json_encode on save and json_decode on hydration; entity property typed `array` or `?array`. Unlimited nesting. Array/object root only (permanent scope boundary). MySQL and PostgreSQL drivers.
- Add JSON query operators to the QueryBuilder — arrow-path syntax in `where()`/`select()`, plus `whereJsonContains`, `whereJsonExists`, `whereJsonMissing`. Makes JSON a first-class EAV / lightweight-NoSQL alternative.
- Introduce `EntityQueryBuilderInterface` (extends `QueryBuilderInterface`, adds `with(string ...$relations)`) and widen `QuerySpecification::apply()`'s parameter type to it so specs express eager loading fluently via the builder, with no extra method on the spec interface. Fixes the pre-existing bug where `matching()` passed the raw inner builder to specs.

### Out of Scope
- Connection pooling, read/write splitting, soft deletes, query caching, polymorphic relationships (explicitly deferred past 1.0).
- Custom column types beyond JSON (UUID, Money, etc.) — post-1.0.
- Typed DTO serialization for JSON columns — `array` / `?array` only for 1.0.
- Top-level JSON scalars and bare literal `null` at the column root — **permanent scope exclusion**, not a 1.0 limitation. Users needing full-JSON-value semantics use a `text` column or a future type-mapper extension.
- JSON indexing DSL — indexes are created via migrations with raw DDL; documented patterns only.
- Composite primary keys — still single-column, just int OR string.

## Success Criteria
- [ ] Entity with string/UUID primary key can be saved, found, updated, deleted, and loaded via relationships.
- [ ] Entity without a declared primary key throws a descriptive exception at metadata-parse time.
- [ ] `$qb->sum('price')`, `min/max/avg/count` return scalars and work alongside WHERE.
- [ ] `$qb->groupBy('category')->having('COUNT(*) > ?', [5])` executes correctly.
- [ ] `$qb->distinct()`, `$qb->union($other)`, `$qb->unionAll($other)` produce correct SQL; union validates matching select count.
- [ ] `$qb->select('users.name as author_name', 'COUNT(*) as total')` works; invalid aliases rejected loudly.
- [ ] `$repo->insertBatch([$e1, $e2, $e3])` issues one multi-row INSERT and fires Creating/Created per entity.
- [ ] Entity with `#[Column(type: 'json')]` round-trips arrays through MySQL and PostgreSQL, including deeply nested structures and nullable `?array` properties.
- [ ] `$qb->where('data->user->name', '=', 'Bob')`, `whereJsonContains`, `whereJsonExists`, and `whereJsonMissing` work on both drivers.
- [ ] A `QuerySpecification` can declare eager-loaded relations; `matching(...)` honors them without awkward ordering.
- [ ] All tests pass via `composer test`; integration tests cover both MySQL and PostgreSQL where relevant.
- [ ] All lint errors (including pre-existing) fixed in touched files per `phpcs` / `php-cs-fixer`.
- [ ] Code follows Marko standards: strict types, no magic methods, no final classes, constructor property promotion, interface-first.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Loud primary key validation (remove `'id'` fallback) | - | completed |
| 002 | String/UUID primary key support | 001 | completed |
| 003 | Aggregate functions on QueryBuilder | 006 | completed |
| 004 | GROUP BY / HAVING on QueryBuilder | 006 | completed |
| 005 | DISTINCT / UNION / UNION ALL on QueryBuilder | - | completed |
| 006 | Column aliasing in select + shared IdentifierValidator | - | completed |
| 007 | Bulk insert via `insertBatch()` | 001 | completed |
| 008 | JSON column type | - | completed |
| 009 | Spec + eager-load composition (fix matching() bug) | - | completed |
| 010 | JSON query operators on QueryBuilder | 006, 008 | completed |

## Architecture Notes

**Judgment calls baked into this plan** (flag for user review):
- `count()` is added to `QueryBuilderInterface` for symmetry with min/max/sum/avg; `Repository::count()` stays and delegates.
- JSON columns support `array` / `?array` properties with unlimited nesting. Array/object root is a permanent scope boundary — top-level scalars and bare literal `null` are forever out, not "deferred." Philosophy: opinionated rails (one sentence: "a JSON column holds a PHP array"), non-limiting (escape hatches: `text` column, custom type mappers post-1.0, raw DDL for indexes). Positioned as a pragmatic in-SQL alternative to EAV and lightweight NoSQL stores — hence querying is in-scope for 1.0 (task 010), indexing is documented-only.
- UNION column-shape validation is done at execute time via select-count comparison; column-type compatibility is documented as the caller's responsibility (matches how every SQL dialect treats it).
- Spec + eager-load composition keeps `QuerySpecification` as a single-method interface. A new `EntityQueryBuilderInterface` (extends `QueryBuilderInterface` with `with()`) becomes the parameter type of `apply()`. Specs express eager loading the same way they express every other query modifier — via the fluent builder. No default-return methods, no sub-interface, no `instanceof`. Aligns with "one way, the right way."

**Principles enforced across all tasks**:
- Interface-first: every public addition lands on an interface before any driver implements it.
- No magic methods: explicit methods for each aggregate, no `__call`.
- Loud errors: unknown alias formats, missing PKs, union shape mismatches all throw with helpful messages.
- Constructor property promotion, strict types, readonly where appropriate.
- Both `marko/database-mysql` and `marko/database-pgsql` drivers updated in lockstep for every query-builder change.

**Testing strategy**:
- TDD per task (Red-Green-Refactor).
- Use `composer test` during development (excludes slow destructive integration tests).
- Integration tests live in the driver packages; interface package has unit tests against fakes/mocks.
- Aggregate, GROUP BY, UNION, JSON, and bulk insert all need driver-level integration tests on MySQL and PostgreSQL.

## Risks & Mitigations
- **Widening PK type breaks existing callers**: `int|string` is a union accepting all current int callers; no caller written today will break. Document in upgrade notes.
- **Aliasing introduces SQL injection surface**: strict regex whitelist (`/^[a-zA-Z_][a-zA-Z0-9_]*$/`) for alias identifiers; reject anything else loudly.
- **UNION shape mismatches are a runtime footgun**: validate select-count at query compile; document column-type matching as caller responsibility.
- **JSON type behaves differently on MySQL vs PostgreSQL**: PostgreSQL has native `jsonb`; MySQL has `JSON`. Driver-level DDL differs; hydration layer stays identical. Integration-test both.
- **Removing `'id'` fallback is a breaking change**: any entity without explicit PK attribute breaks. Pre-1.0 is the right time for this change. Document in upgrade notes.
- **Bulk insert + lifecycle events**: firing events synchronously per entity in a batch is slightly slower but preserves contract; document for users needing raw throughput.
