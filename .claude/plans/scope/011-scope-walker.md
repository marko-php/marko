# Task 011: `ScopeWalker` (multi-axis resolution)

**Status**: pending
**Depends on**: 003, 006, 007, 010
**Retry count**: 0

## Description
The core resolution algorithm. Given a property's declared axes (in priority order), a `ScopedOverrides` companion, and the current `ScopeContext`, walks each axis from its current scope up to the root and returns the first matching override. Returns a sentinel "no override" result so callers can distinguish "found null" from "nothing found."

## Context
- Related files: `packages/scope/src/Resolution/ScopeWalker.php` (new), `packages/scope/src/Resolution/ScopeWalkResult.php` (new — small value object for the not-found sentinel)
- Patterns to follow: Pure-PHP, no I/O. Walker emits the same resolution as the SQL emitters in the driver packages.

## Data Shape Contract
The walker reads from the scope-key-first override map (see tasks 009 / 010):

```php
[
    'geo:eu.de'   => ['name' => 'Hemd'],
    'geo:eu'      => ['name' => 'Shirt-EU'],
    'locale:de'   => ['name_label' => 'Hallo'],
]
```

For property `'name'` with declared axes `['geo', 'locale']` and a `ScopeContext` of `geo=eu.de`, `locale=de-DE`:
1. Walk `geo` from `eu.de → eu`: check `'geo:eu.de'` → hit, return `'Hemd'`.
2. Otherwise walk `locale` from `de-DE → de`: check `'locale:de-DE'` → miss, `'locale:de'` → miss for `name`.
3. Result: `notFound`.

An explicit `null` stored at any walked scope key counts as a hit and short-circuits the walk.

## Requirements (Test Descriptions)
- [ ] `it returns the override at the current scope when one exists`
- [ ] `it returns an ancestor override when no override exists at the current scope`
- [ ] `it returns notFound when no override exists at any walked scope`
- [ ] `it walks axes in declared priority order returning the first axis match`
- [ ] `it skips axes that are not set in ScopeContext`
- [ ] `it preserves an explicit null override and does not fall through it within an axis`
- [ ] `it falls through an axis with no overrides for the property to the next axis (cross-axis fallthrough)`
- [ ] `it stops cross-axis fallthrough when an explicit null is found in an axis (null counts as a found value)`
- [ ] `it returns notFound when no axes are declared and no overrides exist`

## Acceptance Criteria
- `ScopeWalker::walk(ScopedOverrides $overrides, string $property, array $axes): ScopeWalkResult`
- `ScopeWalkResult` carries either `found(mixed $value)` or `notFound()`.
- Pure function — no mutation, no I/O. Fully unit-testable without a DB.

## Implementation Notes
(Left blank — filled in during implementation.)
