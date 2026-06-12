# Task 020: whereIn([]) emits invalid `IN ()`

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Both query builders compile a `whereIn(column, [])` (empty array) to `column IN ()`, which is a SQL syntax error on both MySQL and PostgreSQL. An empty `whereIn` should compile to a guaranteed no-match (e.g. `1 = 0`) so the query is valid and returns zero rows instead of crashing.

> SCOPE CORRECTION (devil's-advocate): the original task also claimed a `whereNotIn()` "twin" should be fixed to a match-all. **There is NO `whereNotIn()` method anywhere in the codebase** — not in `MySqlQueryBuilder`, `PgSqlQueryBuilder`, `QueryBuilderInterface`, or `RepositoryQueryBuilder` (verified). Adding one would be a public-interface change, which `_plan.md` lists Out of Scope, and is far larger than this hardening task. **`whereNotIn` is removed from this task's scope.** If a real `whereNotIn()` is wanted later, file a separate feature task. This task fixes the empty-`whereIn` case ONLY.

## Description-note
An empty `IN` set has a well-defined logical answer (no row is in the empty set). Emitting `IN ()` instead crashes the query. The fix substitutes the constant-false condition so the generated SQL is always valid.

## Context
- Related files (BOTH siblings — the bug exists in each, fix both for parity):
  - `/Users/markshust/Sites/marko/packages/database-mysql/src/Query/MySqlQueryBuilder.php` (`whereIns` property line 31; `whereIn()` line 233; compile loop **lines 950-957** building `$placeholders = array_fill(0, count($whereIn['values']), '?'); $condition = sprintf('%s IN (%s)', $this->quoteIdentifier($whereIn['column']), implode(', ', $placeholders)); $this->bindings = array_merge($this->bindings, $whereIn['values']);` then the `if (!empty($conditions)) { $condition = 'AND ' . $condition; }` join)
  - `/Users/markshust/Sites/marko/packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` (`whereIns` property line 32; `whereIn()` line 232; compile loop **lines 958-967** — structurally identical `sprintf('%s IN (%s)', ...)` over `$whereIn['values']`)
  - Tests: `/Users/markshust/Sites/marko/packages/database-mysql/tests/` and `/Users/markshust/Sites/marko/packages/database-pgsql/tests/` (locate the existing query-builder tests)
- Verified findings (source-confirmed, CURRENT branch):
  - MySQL builder compile loop (lines 950-957): with an empty `values`, `array_fill(0, 0, '?')` yields `[]` → `implode(', ', [])` is `''` → `IN ()`. `array_merge($this->bindings, [])` is a no-op, so for the empty case no bindings are added today either.
  - PgSQL builder compile loop (lines 958-967) is the structurally identical `sprintf('%s IN (%s)', ...)` (it iterates `$whereIn['values']` to build placeholders/bindings). Same `IN ()` bug.
  - There is no `whereNotIn`/`whereNotIns`/`NOT IN` anywhere — do not look for one.
- Patterns to follow:
  - When the `whereIn['values']` array is empty: emit a constant false condition (`1 = 0`) and bind NO placeholders, instead of building `IN (%s)`. Wire it into the SAME `if (!empty($conditions)) { $condition = 'AND ' . $condition; }` join the existing loop uses so chained wheres still compose. A guard at the top of the loop body (e.g. `if ($whereIn['values'] === []) { $condition = '1 = 0'; } else { ...existing IN build... }`) is the lowest-churn shape.
  - Do not add placeholders or merge bindings for the empty case (so `$this->bindings` is untouched for that clause).
  - Apply the SAME fix shape to both builders; keep them byte-for-byte parallel per `.claude/sibling-modules.md`.

## Requirements (Test Descriptions)
For each builder (MySQL and PgSQL):
- [ ] `it compiles whereIn with an empty array to a no-match condition`
- [ ] `it binds no parameters for an empty whereIn`
- [ ] `it still compiles whereIn with a non-empty array to an IN clause`
- [ ] `it composes an empty whereIn with other where clauses using AND`

## Acceptance Criteria
- `whereIn(col, [])` produces valid SQL that returns zero rows on both drivers.
- No `IN ()` ever appears in generated SQL.
- A non-empty `whereIn` still produces an `IN (?, ?, ...)` clause with the right bindings.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- CROSS-TIER REBASE: `MySqlQueryBuilder.php` / `PgSqlQueryBuilder.php` are also touched by Tier 1 / Tier 2 / Tier 3 query-builder tasks. The line anchors above (mysql 950-957 / pgsql 958-967) are CURRENT as of this branch, but re-confirm by searching for the `IN (%s)` `sprintf` in each builder before editing in case a later same-wave task shifts them, and re-run BOTH builders' full test suites after editing.
