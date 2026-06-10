# Task 001: F1 — IdentifierValidator operator allowlist + identifier/delimiter helpers

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Extend the shared `IdentifierValidator` (in `marko/database`) so both query-builder drivers can route every non-raw identifier and every comparison operator through one place. Add a fixed operator allowlist with a loud assertion, a loud identifier assertion (plain and qualified `table.column` forms), and a delimiter-escaping helper. This is the shared foundation tasks 002 and 003 build on.

## Context
- Related files:
  - `packages/database/src/Query/IdentifierValidator.php` (extend; do NOT touch `assertNoDangerousPatterns` / `parseSelectExpression`)
  - `packages/database/src/Exceptions/InvalidColumnException.php` (add `invalidOperator()` factory)
  - `packages/database/tests/Query/IdentifierValidatorTest.php` (existing test file — add to it)
- Patterns to follow:
  - Existing `IdentifierValidator` consts (`IDENTIFIER_PATTERN`, `QUALIFIED_PATTERN`, `AGGREGATE_FUNCTIONS`) and `InvalidColumnException::invalidColumn()` three-part factory.
  - Typed class constant (`public const array OPERATORS = [...]`).
  - Loud exception shape: `message` + `context` + `suggestion`, named args.
  - `assertValidOperator()` must compare against the allowlist with an EXACT match (`in_array($operator, self::OPERATORS, true)`), NOT a substring/prefix check — an attacker passing `"= OR 1=1"` or `"= --"` must be rejected. Do not trim/normalize before comparing in a way that would let `" = "` through unless the drivers also trim before emitting; keep validation and emission consistent.
  - `assertValidIdentifier()` must accept BOTH the plain (`IDENTIFIER_PATTERN`) and qualified (`QUALIFIED_PATTERN`, i.e. `table.column`) forms, and reject everything else (backticks, double-quotes, `;`, `--`, `/* */`, whitespace, `->` JSON arrows). JSON-path columns are NOT validated here — the drivers route those to `compileJsonExtract()` before reaching identifier validation (see tasks 002/003).

## Requirements (Test Descriptions)
- [ ] `it exposes the full comparison operator allowlist (=, !=, <>, <, >, <=, >=, LIKE, NOT LIKE, IN, NOT IN, IS, IS NOT)`
- [ ] `it accepts every allowlisted operator via assertValidOperator without throwing`
- [ ] `it rejects an operator not in the allowlist (e.g. "; DROP TABLE") via assertValidOperator`
- [ ] `it rejects a lowercase-only keyword operator that does not match the allowlist casing`
- [ ] `it rejects an allowlisted operator with surrounding whitespace or trailing SQL (e.g. "= OR 1=1")`
- [ ] `it accepts a plain identifier via assertValidIdentifier`
- [ ] `it accepts a qualified table.column identifier via assertValidIdentifier`
- [ ] `it rejects an identifier containing a backtick, semicolon, or comment marker via assertValidIdentifier`
- [ ] `it escapes an embedded backtick by doubling it via escapeDelimiter`
- [ ] `it escapes an embedded double-quote by doubling it via escapeDelimiter`
- [ ] `it throws InvalidColumnException with a helpful suggestion when an operator is rejected`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
