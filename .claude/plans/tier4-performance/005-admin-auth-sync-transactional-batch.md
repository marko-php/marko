# Task 005: F3 (optional) — Transactional, batched `syncPermissions` / `syncRoles`

**Status**: pending
**Depends on**: [004]
**Retry count**: 0

## Description
`RoleRepository::syncPermissions` performs a `DELETE FROM role_permissions WHERE role_id = ?` followed by one `INSERT` per permission id, with no surrounding transaction — a mid-loop failure leaves a role with a partially-synced permission set, and the per-row inserts are unnecessary round-trips. Wrap the delete plus a single batched/multi-row insert in a conditional transaction. Apply the same treatment to any sibling `syncRoles` method with the same DELETE-then-loop-INSERT shape.

## Description (scope detail)
Cheap correctness+perf improvement folded into the F3 cluster; depends on 004 to avoid concurrent edits to `RoleRepository`. **There is NO `syncRoles` method in `RoleRepository` (verified) — only `syncPermissions` is in scope.** Do not add a `syncRoles` method. `syncPermissions` is already declared on `RoleRepositoryInterface`; this task changes its implementation only and does not alter the interface signature, so existing implementers/mocks are unaffected.

## Context
- Related files:
  - `packages/admin-auth/src/Repository/RoleRepository.php` (`syncPermissions` — DELETE then per-id INSERT loop)
  - `packages/database/src/Connection/TransactionInterface.php` and `Repository::insertBatch` (conditional-transaction guard: only begin/commit when the connection implements `TransactionInterface` and is not already in a transaction — mirror this)
  - `packages/admin-auth/tests/` (RoleRepository tests)
- Patterns to follow:
  - Begin a transaction only when supported (same guard `insertBatch` uses); on success commit, on `Throwable` rollback and rethrow.
  - Replace the per-id INSERT loop with a single multi-row `INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?),(?,?)...` (chunk if the id count is large).
  - Empty permission-id list still clears existing rows (DELETE runs) and issues no INSERT.

## Requirements (Test Descriptions)
- [ ] `it replaces a role's permissions with the new set`
- [ ] `it clears all permissions when given an empty permission id list`
- [ ] `it inserts all new permissions in a single multi-row insert`
- [ ] `it wraps the delete and insert in one transaction when the connection supports transactions`
- [ ] `it rolls back and leaves permissions unchanged when an insert fails mid-sync`
- [ ] `it still syncs when the connection does not support transactions`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
