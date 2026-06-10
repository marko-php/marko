# Task 011: Layout deterministic ambiguous-sort-order detection

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`ComponentCollection::sort()` throws `AmbiguousSortOrderException` from inside the `usort` comparator. PHP's sort (introsort) does not compare every pair, so at scale (17+ elements) an ambiguous pair sharing a `sortOrder` with no `before`/`after` resolution can go uncompared and the ambiguity is missed. Detect ambiguity deterministically OUTSIDE the comparator, then sort with a non-throwing total-order comparator.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/layout/src/ComponentCollection.php` (`sort()` ~131-150; comparator that throws; `applyConstraints()` after)
  - `/Users/markshust/Sites/marko/packages/layout/src/ComponentDefinition.php` (fields `sortOrder`, `before`, `after`, `className`, `slot`)
  - `/Users/markshust/Sites/marko/packages/layout/src/Exceptions/AmbiguousSortOrderException.php` (`forComponents(slot, sortOrder, components)` — reuse)
  - Tests: `/Users/markshust/Sites/marko/packages/layout/tests/` (ComponentCollection tests)
- Patterns to follow:
  - Before sorting, group the components by `sortOrder` (e.g. `$groups[$def->sortOrder][] = $def`). For each group with ≥2 components, if 2 or more of them are unresolved (`before === null && after === null`), throw `AmbiguousSortOrderException::forComponents()` with the slot, the shared sortOrder, and the offending class names (the unresolved ones). This is O(n) grouping — deterministic regardless of count. Match the existing factory signature `forComponents(slot:, sortOrder:, components:)` exactly.
  - REMOVE the `throw` from inside the `usort` comparator entirely. After the ambiguity check passes, `usort` with a comparator that returns `$a->sortOrder <=> $b->sortOrder` with a className tie-break (`?: strcmp($a->className, $b->className)`) so the order is a deterministic TOTAL order and NEVER throws. The className tie-break is required because `usort` is not stable in PHP for equal elements unless the comparator imposes a total order — without it the "sorts deterministically" requirement is not guaranteed.
  - Keep `applyConstraints()` (the second pass) unchanged.
  - Edge case to preserve: a group where exactly ONE component is unresolved and the rest are resolved by `before`/`after` is NOT ambiguous (the resolved ones pin themselves relative to others). Only ≥2 UNRESOLVED components sharing a sortOrder is ambiguous.
  - Test must include a case with 17+ components where exactly one ambiguous pair exists, proving detection no longer depends on which pairs the sort happens to compare. (PHP switches `usort` to a non-comparing fast path / introsort that skips pairs above ~16 elements — hence the 17+ threshold is load-bearing.)

## Requirements (Test Descriptions)
- [ ] `it detects an ambiguous sort-order pair among 17 or more components`
- [ ] `it throws AmbiguousSortOrderException naming the conflicting components`
- [ ] `it does not throw when components share a sort order but all but one are resolved by before/after`
- [ ] `it does not throw when all sort orders are unique`
- [ ] `it sorts resolved components by sort order deterministically`
- [ ] `it still applies before/after constraints after the ambiguity check`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
