# Task 004: PostgreSQL Connection Implementation

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Implement the PostgreSQL connection class using PDO. This provides the concrete implementation of ConnectionInterface for PostgreSQL databases with proper DSN construction and PostgreSQL-specific options.

## Context
- Related files: packages/database-pgsql/src/Connection/PgSqlConnection.php
- Patterns to follow: Same patterns as MySqlConnection
- Must handle PostgreSQL-specific DSN format (host, port, dbname)

## Requirements (Test Descriptions)
- [x] `it implements ConnectionInterface`
- [x] `it constructs proper PostgreSQL DSN from config`
- [x] `it connects lazily on first query`
- [x] `it sets PDO error mode to exceptions`
- [x] `it sets client encoding from config`
- [x] `it executes raw SQL queries with parameter binding`
- [x] `it prepares statements for repeated execution`
- [x] `it throws ConnectionException on connection failure with helpful message`
- [x] `it properly disconnects and releases resources`

## Acceptance Criteria
- All requirements have passing tests
- Connection is lazy (doesn't connect until needed)
- Proper error handling with meaningful exceptions
- PDO configured for exceptions, not silent failures

## Implementation Notes
(Left blank - filled in by programmer during implementation)
