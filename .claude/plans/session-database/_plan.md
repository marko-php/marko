# Plan: session-database Package

## Created
2026-02-23

## Status
ready

## Objective
Build `marko/session-database` — a database-backed session handler implementing SessionHandlerInterface, following the session-file driver pattern.

## Scope
### In Scope
- DatabaseSessionHandler implementing SessionHandlerInterface
- Sessions table schema (id, payload, last_activity)
- Garbage collection of expired sessions
- Package scaffolding following session-file pattern

### Out of Scope
- Migration generation (manually create table)
- Session encryption

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding and module tests | - | pending |
| 002 | DatabaseSessionHandler implementation and tests | 001 | pending |

## Architecture Notes
- Uses ConnectionInterface from marko/database for queries
- Sessions stored in `sessions` table: id (VARCHAR PK), payload (TEXT), last_activity (INT timestamp)
- GC deletes rows where last_activity < (now - max_lifetime)
- Follows FileSessionHandler pattern exactly for method signatures
