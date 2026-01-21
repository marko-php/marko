# Task 004: PostgreSQL Connection Implementation

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Implement the PostgreSQL connection class using PDO. This provides the concrete implementation of ConnectionInterface for PostgreSQL databases with proper DSN construction and PostgreSQL-specific options.

## Context
- Related files: packages/database-pgsql/src/Connection/PgSqlConnection.php
- Patterns to follow: Same patterns as MySqlConnection
- Must handle PostgreSQL-specific DSN format (host, port, dbname)

## Requirements (Test Descriptions)
- [ ] `it implements ConnectionInterface`
- [ ] `it constructs proper PostgreSQL DSN from config`
- [ ] `it connects lazily on first query`
- [ ] `it sets PDO error mode to exceptions`
- [ ] `it sets client encoding from config`
- [ ] `it executes raw SQL queries with parameter binding`
- [ ] `it prepares statements for repeated execution`
- [ ] `it throws ConnectionException on connection failure with helpful message`
- [ ] `it properly disconnects and releases resources`

## Acceptance Criteria
- All requirements have passing tests
- Connection is lazy (doesn't connect until needed)
- Proper error handling with meaningful exceptions
- PDO configured for exceptions, not silent failures

## Implementation Notes
(Left blank - filled in by programmer during implementation)
