# Task 004: Update Repository Tests

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update Repository unit tests that use entities with camelCase properties. After the core change, the Repository generates SQL using snake_case column names, so mock connections and assertions need updating.

## Context
- `packages/database/tests/Repository/RepositoryTest.php`:
  - `RepositoryTestUser` has `$isActive` with bare `#[Column]` — column becomes `is_active` instead of `isActive`
  - `$email` with `#[Column('email_address')]` — explicitly named, no change needed
  - There are **23 occurrences** of `isActive` in this file that need review. Affected areas:
    - Line 188: Comment `'isActive' maps to 'isActive' column` — update comment to reflect snake_case
    - Lines 194, 233, 282: Mock connection storage arrays with `'isActive'` key — change to `'is_active'`
    - Lines 310, 311, 327, 328, 343: Mock query return arrays with `'isActive'` key — change to `'is_active'`
    - Line 334: `findBy(['isActive' => true])` — this uses the PHP property name as the criteria key, which Repository::findBy maps to column name via `$propertyToColumn`. The call stays as `findBy(['isActive' => true])` (property name), but the generated SQL will now contain `is_active = ?` instead of `isActive = ?`, so mock SQL matching must update accordingly.
    - Lines 412, 452, 527, 626, 665, 724, 819: Various mock data arrays with `'isActive'` key — change to `'is_active'`
    - Line 577: `->not->toContain('isActive')` — this checks SQL SET clause; update to `'is_active'`
  - NOTE: Lines that reference `$user->isActive` (PHP property access) do NOT change — only column name string keys in mock data arrays and SQL assertions change

- `packages/database/tests/Repository/RepositoryLifecycleEventTest.php`:
  - `LifecycleTestItem` only has `$id` and `$name` (single-word properties) — no changes needed
  - Verify test still passes after the core change

## Requirements (Test Descriptions)
- [ ] `it uses snake_case column names in Repository SQL generation assertions`
- [ ] `it uses snake_case column names in mock connection storage arrays`
- [ ] `it preserves explicit Column name override (email_address) in Repository tests`
- [ ] `it passes all Repository tests after column name updates`

## Acceptance Criteria
- All tests in `packages/database/tests/Repository/` pass
- All `isActive` column references in mock code updated to `is_active`
- Explicit `email_address` mapping unchanged
- RepositoryLifecycleEventTest passes without changes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
