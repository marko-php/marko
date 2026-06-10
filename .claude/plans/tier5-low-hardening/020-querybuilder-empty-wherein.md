# Task 020: whereIn([]) emits invalid `IN ()`

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Both query builders compile a `whereIn(column, [])` (empty array) to `column IN ()`, which is a SQL syntax error on both MySQL and PostgreSQL. The same applies to `whereNotIn(column, [])`. An empty `whereIn` should compile to a guaranteed no-match (e.g. `1 = 0`) so the query is valid and returns zero rows; an empty `whereNotIn` should compile to a match-all (e.g. `1 = 1`) so it returns all rows.

## Description-note
An empty `IN` set has a well-defined logical answer (no row is in the empty set; every row is not-in the empty set). Emitting `IN ()` instead crashes the query. The fix substitutes the constant-condition equivalents so the generated SQL is always valid.

## Context
- Related files (BOTH siblings — the bug exists in each, fix both for parity):
  - `/Users/markshust/Sites/marko/packages/database-mysql/src/Query/MySqlQueryBuilder.php` (`whereIn()` ~207; compile loop ~824-838 building `sprintf('%s IN (%s)', quoteIdentifier(col), implode(', ', array_fill(0, count(values), '?')))`; the `whereNotIn` twin)
  - `/Users/markshust/Sites/marko/packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` (`whereIn()` ~206; compile loop ~825-834 building the same `'%s IN (%s)'`; the `whereNotIn` twin)
  - Tests: `/Users/markshust/Sites/marko/packages/database-mysql/tests/` and `/Users/markshust/Sites/marko/packages/database-pgsql/tests/` (locate the existing query-builder tests)
- Verified findings (source-confirmed):
  - MySQL builder compile loop: `$placeholders = array_fill(0, count($whereIn['values']), '?'); $condition = sprintf('%s IN (%s)', $this->quoteIdentifier($whereIn['column']), implode(', ', $placeholders));`. With an empty `values`, `array_fill(0, 0, '?')` yields `[]` → `IN ()`.
  - PgSQL builder compile loop is the structurally identical `sprintf('%s IN (%s)', ...)` over `$whereIn['values']`.
  - Confirm whether `whereNotIn` is a separate property/loop or a flag on the same `whereIns` entries (read each builder's `whereNotIn()` to see how negation is stored) before writing the fix so the empty-array branch is applied to BOTH in/not-in.
- Patterns to follow:
  - When the `values` array is empty: for `whereIn`, emit a constant false condition (`1 = 0`) and bind NO placeholders; for `whereNotIn`, emit a constant true condition (`1 = 1`) and bind no placeholders. Wire it into the same boolean/`AND` joining the existing loop uses so chained wheres still compose.
  - Do not add placeholders for the empty case (so `$this->bindings` is untouched for that clause).
  - Apply the SAME fix shape to both builders; keep them byte-for-byte parallel per `.claude/sibling-modules.md`.

## Requirements (Test Descriptions)
For each builder (MySQL and PgSQL):
- [ ] `it compiles whereIn with an empty array to a no-match condition`
- [ ] `it compiles whereNotIn with an empty array to a match-all condition`
- [ ] `it binds no parameters for an empty whereIn`
- [ ] `it still compiles whereIn with a non-empty array to an IN clause`

## Acceptance Criteria
- `whereIn(col, [])` produces valid SQL that returns zero rows on both drivers; `whereNotIn(col, [])` produces valid SQL that returns all rows.
- No `IN ()` ever appears in generated SQL.
- A non-empty `whereIn` still produces an `IN (?, ?, ...)` clause with the right bindings.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- CROSS-TIER REBASE: `MySqlQueryBuilder.php` / `PgSqlQueryBuilder.php` are also touched by Tier 1 / Tier 2 / Tier 3 query-builder tasks. This task must be rebased sequentially onto whatever query-builder changes those tiers land first — do NOT assume the line numbers above are still exact at implementation time; re-locate the `IN (%s)` compile loop in each builder before editing, and re-run BOTH builders' full test suites after rebasing.
