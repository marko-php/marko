# Task 009: Spec + Eager-Load Composition

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Let `QuerySpecification` express eager loading naturally — via the same fluent builder it already uses for `where()`, `orderBy()`, etc. — without bloating the spec interface. `QuerySpecification` stays a single-method contract (`apply()`).

**Approach:** widen the parameter type of `apply()` from `QueryBuilderInterface` to a new `EntityQueryBuilderInterface` that extends it and adds `with(string ...$relations): static`. `RepositoryQueryBuilder` implements the new interface (it already has `with()` at line 200). `RepositoryQueryBuilder::matching()` passes `$this` to each spec's `apply()` instead of the inner raw `$this->queryBuilder` (line 215) — this is the one-line bug that currently prevents specs from reaching `with()` at all.

Specs that want eager loading simply call `$builder->with('author', 'tags')` inside their existing `apply()` body — same style as every other query modifier. Specs that don't want eager loading are completely unaffected. No default-return methods. No sub-interface. No `instanceof` checks.

This also fixes the pre-existing bug noted in review: `matching()` previously passed the raw inner builder to specs, so even `$repo->with('rel')->matching($spec)` callers couldn't get eager loads to stick through spec-built queries. Fixing the builder passed to `apply()` fixes both issues at once.

**Breaking change (acceptable pre-1.0):** `QuerySpecification::apply()`'s parameter type changes. Every existing spec implementation updates its parameter type hint. No method-count change on the interface itself.

## Context
- Related files:
  - `packages/database/src/Query/QuerySpecification.php` — change `apply(QueryBuilderInterface $builder)` to `apply(EntityQueryBuilderInterface $builder)`. Still a single-method interface.
  - `packages/database/src/Query/EntityQueryBuilderInterface.php` — NEW. Extends `QueryBuilderInterface`. Adds `with(string ...$relations): static`. This is the spec-facing contract — specs work against entity queries, never raw query builders.
  - `packages/database/src/Repository/RepositoryQueryBuilder.php` — declare `implements EntityQueryBuilderInterface` (already has the `with()` method). Fix `matching()` at line ~215 to pass `$this` instead of `$this->queryBuilder`.
  - `packages/database/src/Repository/Repository.php` — audit any `matching()` entry point to ensure specs receive the `RepositoryQueryBuilder` wrapper, not a raw builder.
  - `packages/database/src/Entity/RelationshipLoader.php` — already handles batched loading. No changes.
  - Any existing `QuerySpecification` implementations in fixtures or other packages — update parameter type hint.
- Patterns to follow: existing fluent builder usage inside `apply()` methods. After this change, eager loading is identical in spirit to every other query-modifying call.

## Requirements (Test Descriptions)
- [x] `it exposes EntityQueryBuilderInterface as the parameter type of QuerySpecification::apply()`
- [x] `it lets a spec call $builder->with('relation') inside apply() to declare eager loading`
- [x] `it eager-loads relationships declared by a spec via the fluent builder`
- [x] `it eager-loads nested relationship paths declared by a spec (e.g. "author.profile")`
- [x] `it merges eager loads across multiple specs without duplicating queries`
- [x] `it still supports explicit $repo->with(...)->matching(...) callers (fixes pre-existing bug where matching() passed the raw inner builder)`
- [x] `it merges call-site $repo->with(...) relationships with spec-declared with() relationships without duplicates`
- [x] `it does not execute N+1 queries when a spec declares eager loads`
- [x] `it validates each spec-declared relationship name against entity metadata and throws on unknown names (consistent with Repository::with())`
- [x] `existing single-method QuerySpecification implementations continue to compile after updating only the apply() parameter type hint`

## Acceptance Criteria
- Backward compatible: specs that don't override `with()` behave exactly as before.
- Spec-declared and call-site-declared eager loads compose (both apply).
- No duplicate queries when the same relationship appears in multiple specs.
- Tests verify query count to catch N+1 regressions.

## Implementation Notes

**New file:** `packages/database/src/Query/EntityQueryBuilderInterface.php` — extends `QueryBuilderInterface`, adds `with(string ...$relations): static`. This is the spec-facing contract.

**`QuerySpecification::apply()` parameter type** changed from `QueryBuilderInterface` to `EntityQueryBuilderInterface`. PHP's contravariance rules allow existing implementations typed as `apply(QueryBuilderInterface $builder)` to continue compiling without modification — a wider parameter type in the implementation is valid.

**`RepositoryQueryBuilder`** now declares `implements EntityQueryBuilderInterface`. Added proxy methods for `distinct()`, `union()`, `unionAll()`, `getColumnCount()`, and `compileSubquery()` that were added to `QueryBuilderInterface` on this branch. The `with()` method was updated to merge-and-deduplicate rather than replace. The `eagerLoadRelationships()` private method was updated to use `RelationshipLoader::loadNested()` with a parsed relationship tree, enabling dot-notation nested paths. The `matching()` method was fixed to pass `$this` to `spec->apply()` instead of `$this->queryBuilder`.

**`Repository::matching()`** was refactored to construct a `RepositoryQueryBuilder` wrapper (pre-seeded with any call-site `pendingRelationships` from `$repo->with(...)->matching(...)`) and pass it to each spec's `apply()`. After all specs run, `getEntities()` is called on the wrapper, which triggers eager loading of all accumulated relationships (both call-site and spec-declared, deduplicated).

**Validation:** `RepositoryQueryBuilder::with()` validates each relationship name against entity metadata (consistent with `Repository::with()`), throwing `RepositoryException::unknownRelationship()` on unknown names.

**Tests added:** `packages/database/tests/Query/SpecEagerLoadCompositionTest.php` — 10 tests covering all requirements including N+1 detection via `whereInCount` tracking.
