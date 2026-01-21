# Task 027: Transaction Support

**Status**: pending
**Depends on**: 002, 003, 004
**Retry count**: 0

## Description
Implement transaction support in the connection classes with both callback-based (recommended) and manual (begin/commit/rollback) APIs. Transactions ensure atomic operations.

## Context
- Related files: Connection classes in both driver packages
- Patterns to follow: TransactionInterface from task 002
- Callback style auto-commits on success, rolls back on exception

## Requirements (Test Descriptions)
- [ ] `it implements beginTransaction() method`
- [ ] `it implements commit() method`
- [ ] `it implements rollback() method`
- [ ] `it implements inTransaction() method returning boolean`
- [ ] `it implements transaction(callable) method`
- [ ] `it auto-commits when callback completes successfully`
- [ ] `it auto-rolls back when callback throws exception`
- [ ] `it re-throws exception after rollback`
- [ ] `it returns callback return value on success`
- [ ] `it prevents nested transactions (throws exception)`
- [ ] `it works identically in MySQL and PostgreSQL`

## Acceptance Criteria
- All requirements have passing tests
- Callback API is preferred and documented
- Manual API available for complex cases
- Both drivers behave identically

## Implementation Notes
(Left blank - filled in by programmer during implementation)
