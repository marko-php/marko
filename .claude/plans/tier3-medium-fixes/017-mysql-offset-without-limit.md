# Task 017: MySQL query builder emits valid SQL for offset without limit

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`MySqlQueryBuilder::buildLimitOffsetClause()` appends ` OFFSET n` whenever an offset is set, independent of whether a limit is set. MySQL has no standalone `OFFSET` clause — `... OFFSET 10` with no preceding `LIMIT` is a syntax error. (The pgsql builder accepts a bare `OFFSET`, so this is MySQL-specific.) Fix: when an offset is set without a limit, emit the MySQL idiom (`LIMIT 18446744073709551615 OFFSET n`) or otherwise valid SQL. `limit + offset` together must stay unchanged.

## Context
- Related files:
  - `packages/database-mysql/src/Query/MySqlQueryBuilder.php` (`buildLimitOffsetClause()` lines 943-956 — `' LIMIT ' . $this->limitValue` when `limitValue !== null` at 947-949; `' OFFSET ' . $this->offsetValue` when `offsetValue !== null` at 951-953. Props `?int $limitValue` (80), `?int $offsetValue` (82); setters `limit()` (417), `offset()` (425); `count()` saves/restores both at 620-632)
- Patterns to follow:
  - Only change the `offset-set && limit-null` branch. When `limitValue === null && offsetValue !== null`, emit `LIMIT 18446744073709551615 OFFSET n` (MySQL's documented "all rows from offset" idiom). Leave the `limit + offset` and `limit-only` outputs byte-identical to today.
  - Use the existing integer property values directly (already typed `?int`, no injection risk). No new config, no new exception.

### Cross-tier rebase note (this file is touched by Tiers 1, 3, and 5)
`MySqlQueryBuilder.php` is also edited by **Tier 1 (SQL-injection hardening)** and **Tier 5 (whereIn-empty handling)**. All three tiers touch SQL-string assembly in this one file. The clauses differ (Tier 1 = identifier/binding safety, Tier 3 = limit/offset, Tier 5 = `whereIn([])`), so the conflicts are mechanical. Implementer must rebase this task onto whichever of Tier 1 / Tier 5 has merged first and re-run the MySQL builder suite before committing. Keep this change surgically scoped to `buildLimitOffsetClause()` to minimize overlap.

### Verification note (read at planning time)
Confirmed against source: `buildLimitOffsetClause()` at 943-956 emits the two clauses independently; a bare offset produces ` OFFSET n` with no `LIMIT`. Line numbers match the original finding exactly.

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
