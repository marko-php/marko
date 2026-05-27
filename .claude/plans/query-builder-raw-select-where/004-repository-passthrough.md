# Task 004: RepositoryQueryBuilder — Passthrough `selectRaw` and `whereRaw`

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Add `selectRaw` and `whereRaw` to the delegating `RepositoryQueryBuilder` — same pattern as the existing `having()` delegation. Methods discard the inner builder's `static` return value and return `$this` so chaining works through the wrapper itself.

## Context

### Files to edit
- `packages/database/src/Repository/RepositoryQueryBuilder.php` (read first — 394 lines, delegates everything to an internal `private readonly QueryBuilderInterface $queryBuilder`).
- `packages/database/tests/Repository/RepositoryQueryBuilderEnhancedTest.php` (extend; OR create a sibling `RepositoryQueryBuilderRawTest.php` if the existing file becomes unwieldy — programmer's call).

### Verified prior art (the closest existing delegation pattern)

Lines 170–175 of `RepositoryQueryBuilder.php` — the `having()` delegation:

```php
public function having(string $expression, array $bindings = []): static
{
    $this->queryBuilder->having($expression, $bindings);
    return $this;
}
```

(Note: the plan's earlier draft incorrectly described an `orderByRaw` delegation at lines 232–242. That method does NOT exist in `RepositoryQueryBuilder` or anywhere else in the codebase. Use the `having()` delegation as the template instead.)

### New methods to add

Place adjacent to the corresponding non-raw siblings:
- `selectRaw` immediately after `select()` (around line 55).
- `whereRaw` immediately after `having()` (around line 175) — keeping all raw-expression delegations together — OR after `orWhere()` (around line 121) to keep WHERE-shaping methods together. Programmer's call; either placement is fine, but document the choice.

```php
public function selectRaw(
    string $expression,
    array $bindings = [],
): static {
    $this->queryBuilder->selectRaw($expression, $bindings);
    return $this;
}

public function whereRaw(
    string $expression,
    array $bindings = [],
): static {
    $this->queryBuilder->whereRaw($expression, $bindings);
    return $this;
}
```

### Test setup considerations
The existing `RepositoryQueryBuilderEnhancedTest.php` file uses `makeRqbStubBuilder()` (line ~78), an anonymous class implementing `QueryBuilderInterface`. Task 001 already added no-op `selectRaw`/`whereRaw` methods to this stub. For tests in THIS task that need to assert delegation behavior (e.g., bindings forwarded correctly, exceptions propagated), extend the stub to record `selectRaw`/`whereRaw` call arguments — same pattern the stub already uses for `wheresCalled` and `orderByCalled`.

The denylist enforcement lives on the inner builder; the wrapper relies on it. Tests assert the wrapper propagates exceptions correctly (no swallowing).

## Requirements (Test Descriptions)

### Basic delegation
- [x] `selectRaw delegates to the inner query builder with the same expression and bindings`
- [x] `selectRaw returns the wrapper for fluent chaining`
- [x] `selectRaw propagates InvalidColumnException from the inner builder`
- [x] `whereRaw delegates to the inner query builder with the same expression and bindings`
- [x] `whereRaw returns the wrapper for fluent chaining`
- [x] `whereRaw propagates InvalidColumnException from the inner builder`

### Chaining through the wrapper
- [x] `the wrapper can chain selectRaw and whereRaw alongside the existing methods (e.g. select.selectRaw.where.whereRaw.orderBy)`

### QuerySpecification composition (smoke test for the real-world use case)
- [x] `a QuerySpecification that calls $builder->selectRaw(...) inside its apply() method works correctly when invoked via matching()`
- [x] `a QuerySpecification that calls $builder->whereRaw(...) inside its apply() method works correctly when invoked via matching()`

## Acceptance Criteria
- All requirements have passing tests.
- Implementation is the minimal 4-line delegation per method, matching the `having()` shape.
- No mutation to the `having()` delegation or other unrelated methods.
- `composer test` for `packages/database` is green.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
- Both `selectRaw` and `whereRaw` were already implemented in `RepositoryQueryBuilder.php` (lines 57–64 and 132–139) as part of Task 001 parallel work. No implementation changes were needed.
- The stub in `RepositoryQueryBuilderEnhancedTest.php` was extended to record `selectRaw`/`whereRaw` calls (added `$selectRawCalled`, `$whereRawCalled`, `$selectRawShouldThrow`, `$whereRawShouldThrow` tracking properties) following the existing `wheresCalled`/`orderByCalled` pattern.
- 9 new tests were added directly to the existing `RepositoryQueryBuilderEnhancedTest.php` file (file is not unwieldy, so no sibling file was needed).
- All 9 new tests passed immediately because the implementation was already in place.
