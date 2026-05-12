# Task 002: Add `extends`/`extenders` fields to `EntityMetadata`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Extend `EntityMetadata` with two new fields: `$extends` (`?class-string` — set on an extender; points at the parent entity class) and `$extenders` (`array<class-string>` — set on a parent; lists registered extender classes). Both default to `null` / `[]` so existing call sites are unaffected. Add small helpers: `isExtender(): bool`, `isExtended(): bool`, `withExtenders(array $extenders): self`.

## Context
- Related files:
  - `packages/database/src/Entity/EntityMetadata.php` (modify)
  - `packages/database/tests/Entity/EntityMetadataTest.php` (add or extend)
- Patterns to follow:
  - `EntityMetadata` is `readonly` — use a `withExtenders()` immutable update method (mirrors `Schema\Table::withColumn()` etc.)
  - Add fields as final constructor params with defaults so existing positional callers still work; prefer named arguments in new code

## Requirements (Test Descriptions)
- [x] `it constructs with no extends and empty extenders by default`
- [x] `it accepts an extends class-string`
- [x] `it accepts an extenders array`
- [x] `it reports isExtender true when extends is set`
- [x] `it reports isExtender false when extends is null`
- [x] `it reports isExtended true when extenders is non-empty`
- [x] `it reports isExtended false when extenders is empty`
- [x] `it returns a new instance from withExtenders without mutating the original`

## Acceptance Criteria
- All requirements have passing tests
- `EntityMetadata` still `readonly`
- New fields documented in the class-level docblock
- Existing tests that construct `EntityMetadata` continue to pass without modification
- Code follows code standards
