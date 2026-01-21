# Task 027: Transaction Support

**Status**: completed
**Depends on**: 002, 003, 004
**Retry count**: 0

## Description
Implement transaction support in the connection classes with both callback-based (recommended) and manual (begin/commit/rollback) APIs. Transactions ensure atomic operations.

## Context
- Related files: Connection classes in both driver packages
- Patterns to follow: TransactionInterface from task 002
- Callback style auto-commits on success, rolls back on exception

## Requirements (Test Descriptions)
- [x] `it implements beginTransaction() method`
- [x] `it implements commit() method`
- [x] `it implements rollback() method`
- [x] `it implements inTransaction() method returning boolean`
- [x] `it implements transaction(callable) method`
- [x] `it auto-commits when callback completes successfully`
- [x] `it auto-rolls back when callback throws exception`
- [x] `it re-throws exception after rollback`
- [x] `it returns callback return value on success`
- [x] `it prevents nested transactions (throws exception)`
- [x] `it works identically in MySQL and PostgreSQL`

## Acceptance Criteria
- All requirements have passing tests
- Callback API is preferred and documented
- Manual API available for complex cases
- Both drivers behave identically

## Implementation Notes
Implemented transaction support for both MySqlConnection and PgSqlConnection:

1. Added `inTransaction()` method to TransactionInterface (was missing from the interface)
2. Created TransactionException in packages/database/src/Exceptions/ for nested transaction errors
3. Implemented all transaction methods in MySqlConnection:
   - beginTransaction(), commit(), rollback(), inTransaction(), transaction(callable)
   - Uses PDO's native transaction methods
   - Checks for nested transactions and throws TransactionException
4. Implemented identical transaction methods in PgSqlConnection
5. Both implementations use the same pattern:
   - transaction(callable) wraps beginTransaction/commit/rollback
   - Auto-commits on success, auto-rolls back on exception
   - Re-throws the original exception after rollback
   - Returns the callback's return value

Files modified:
- packages/database/src/Connection/TransactionInterface.php (added inTransaction())
- packages/database/src/Exceptions/TransactionException.php (new)
- packages/database-mysql/src/Connection/MySqlConnection.php
- packages/database-pgsql/src/Connection/PgSqlConnection.php
- packages/database-mysql/tests/Connection/MySqlConnectionTest.php
- packages/database-pgsql/tests/Connection/PgSqlConnectionTest.php
