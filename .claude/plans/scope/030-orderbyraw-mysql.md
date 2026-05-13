# Task 030: Implement `orderByRaw` on `MySqlQueryBuilder`

**Status**: completed
**Depends on**: 029

## Description
Implement `orderByRaw(string $expression, string $direction): static` on `Marko\Database\MySql\Query\MySqlQueryBuilder`. The expression is appended to the existing `$orders` array as a raw entry that bypasses `quoteIdentifier()` in `buildOrderByClause()`.

## Context
- Related files: `packages/database-mysql/src/Query/MySqlQueryBuilder.php`
- Patterns to follow: Existing `orderBy()` method; the `$orders` array currently stores `['column' => string, 'direction' => string]`. Extend with a discriminator (`'raw' => bool`) or two separate slots.

## Requirements (Test Descriptions)
- [ ] `it appends a raw expression to the order clause without quoting`
- [ ] `it rejects expressions containing semicolons`
- [ ] `it rejects expressions containing SQL comments (-- or /*)`
- [ ] `it preserves direction asc or desc on the emitted ORDER BY`
- [ ] `it composes correctly with a regular orderBy call before or after`
- [ ] `it emits a single ORDER BY clause with comma-separated entries for mixed regular and raw orders`

## Acceptance Criteria
- Method on `MySqlQueryBuilder` matches the interface signature.
- `buildOrderByClause()` is updated to render raw entries verbatim (no `quoteIdentifier`).
- Dangerous-pattern rejection reuses or mirrors `IdentifierValidator::rejectDangerousPatterns`.
- Direction normalised to uppercase ASC/DESC; invalid values default to ASC (same as existing `orderBy`).

## Implementation Notes
(Left blank — filled in during implementation.)
