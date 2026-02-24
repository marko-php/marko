# Task 002: DatabaseSessionHandler Implementation and Tests

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Implement DatabaseSessionHandler with all SessionHandlerInterface methods using ConnectionInterface for database access. Store sessions in a `sessions` table with id, payload, and last_activity columns.

## Context
- Reference: packages/session-file/src/Handler/FileSessionHandler.php (same method signatures)
- Reference: packages/session-file/tests/ (same test pattern)
- Uses ConnectionInterface for database queries
- Table: sessions (id VARCHAR PRIMARY KEY, payload TEXT, last_activity INTEGER)

## Requirements (Test Descriptions)
- [ ] `it implements SessionHandlerInterface`
- [ ] `it opens session successfully`
- [ ] `it closes session successfully`
- [ ] `it reads existing session data`
- [ ] `it returns empty string for missing session`
- [ ] `it writes session data`
- [ ] `it updates existing session data`
- [ ] `it destroys existing session`
- [ ] `it returns true when destroying missing session`
- [ ] `it garbage collects expired sessions`
- [ ] `it returns count of deleted sessions from gc`
- [ ] `it preserves recent sessions during gc`
