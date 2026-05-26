# Task 004: Database-PgSql Page — Cross-Link to Variants Guide

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Add a short subsection to `docs/src/content/docs/packages/database-pgsql.md` that points readers to the "Wire-compatible database variants" section of `packages/database.md`. This makes the variant story discoverable from the driver page without duplicating content.

## Context
- **Why this is its own task:** Depends on task 003 — the link target must exist before this can land.
- **Subsection scope:** 2–4 sentences total. Title something like "Postgres-wire-compatible databases (CockroachDB, YugabyteDB, etc.)". One-line explanation that `PgSqlConnection` speaks pure PDO over the postgres wire protocol so other postgres-wire databases can reuse it, then a link to the database guide for the full pattern.
- **Placement:** Near the bottom of the page, after primary install/usage content but before any troubleshooting/FAQ section.
- **Do not duplicate the worked example.** Just the link and one-line motivation.

## Requirements (Test Descriptions)
*Docs-only task — "tests" are content-quality assertions verified by review.*

- [ ] `the new subsection has a clear heading mentioning postgres-wire-compatible databases`
- [ ] `the subsection is short (2-4 sentences, no duplicated code examples)`
- [ ] `the subsection links to /docs/packages/database/#wire-compatible-database-variants (anchor locked by task 003)`
- [ ] `the subsection notes that PgSqlConnection is reusable because it is dialect-clean`

## Acceptance Criteria
- New subsection added to `docs/src/content/docs/packages/database-pgsql.md` in the appropriate position (after "Driver-Specific Notes" or before "API Reference" — match existing flow).
- The cross-link uses the exact anchor `/docs/packages/database/#wire-compatible-database-variants` (locked by task 003).
- `npm --prefix docs run build` passes with no new warnings or broken-link errors.
- No code examples duplicated from `database.md`.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
