# Task 017: MySQL query builder emits valid SQL for offset without limit

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`MySqlQueryBuilder::buildLimitOffsetClause()` appends ` OFFSET n` whenever an offset is set, independent of whether a limit is set. MySQL has no standalone `OFFSET` clause — `... OFFSET 10` with no preceding `LIMIT` is a syntax error. (The pgsql builder accepts a bare `OFFSET`, so this is MySQL-specific.) Fix: when an offset is set without a limit, emit the MySQL idiom (`LIMIT 18446744073709551615 OFFSET n`) or otherwise valid SQL. `limit + offset` together must stay unchanged.

## Context
- Related files:
  - `packages/database-mysql/src/Query/MySqlQueryBuilder.php` (`buildLimitOffsetClause()` lines **1068-1081** — `' LIMIT ' . $this->limitValue` when `limitValue !== null` at 1072-1074; `' OFFSET ' . $this->offsetValue` when `offsetValue !== null` at 1076-1078. Props `?int $limitValue`, `?int $offsetValue`; setters `limit()` (~504), `offset()` (~512); `count()` saves/restores both — do not rely on the count() line numbers, locate behaviorally)
- Patterns to follow:
  - Only change the `offset-set && limit-null` branch. When `limitValue === null && offsetValue !== null`, emit `LIMIT 18446744073709551615 OFFSET n` (MySQL's documented "all rows from offset" idiom). Leave the `limit + offset` and `limit-only` outputs byte-identical to today.
  - Use the existing integer property values directly (already typed `?int`, no injection risk). No new config, no new exception.

### Cross-tier rebase note (this file is touched by Tiers 1, 3, and 5)
`MySqlQueryBuilder.php` is also edited by **Tier 1 (SQL-injection hardening)** and **Tier 5 (whereIn-empty handling)**. All three tiers touch SQL-string assembly in this one file. The clauses differ (Tier 1 = identifier/binding safety, Tier 3 = limit/offset, Tier 5 = `whereIn([])`), so the conflicts are mechanical. Implementer must rebase this task onto whichever of Tier 1 / Tier 5 has merged first and re-run the MySQL builder suite before committing. Keep this change surgically scoped to `buildLimitOffsetClause()` to minimize overlap.

### Verification note (read at MERGED-source review time — line numbers corrected)
Confirmed against MERGED source: `buildLimitOffsetClause()` is now at lines **1068-1081** (the original cited 943-956 has DRIFTED after Tier 1's edits to this file). The body still emits the two clauses independently; a bare offset produces ` OFFSET n` with no `LIMIT`. The bug and the fix are unchanged — only the line numbers moved.

## Requirements (Test Descriptions)
- [ ] `it produces valid MySQL SQL for an offset without a limit`
- [ ] `it does not emit a bare OFFSET clause without a LIMIT`
- [ ] `it leaves limit and offset together unchanged`
- [ ] `it leaves a limit without an offset unchanged`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
