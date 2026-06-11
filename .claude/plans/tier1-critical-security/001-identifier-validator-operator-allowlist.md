# Task 001: F1 — IdentifierValidator operator allowlist + identifier/delimiter helpers

**Status**: complete
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
  - CRITICAL — the three new methods (`assertValidOperator`, `assertValidIdentifier`, `escapeDelimiter`) MUST be declared `public static` to match the entire existing `IdentifierValidator` API (`isValidIdentifier()`, `assertNoDangerousPatterns()`, `parseSelectExpression()` are all `static`). Tasks 002/003 call them statically — `IdentifierValidator::assertValidIdentifier(...)`, exactly as `MySqlQueryBuilder` already calls `IdentifierValidator::assertNoDangerousPatterns(...)` and `::isValidIdentifier(...)`. If they are implemented as instance methods the driver-side calls will fatal. This is a hard cross-task contract.
  - `assertValidOperator()` must compare against the allowlist with an EXACT match (`in_array($operator, self::OPERATORS, true)`), NOT a substring/prefix check — an attacker passing `"= OR 1=1"` or `"= --"` must be rejected. Do not trim/normalize before comparing in a way that would let `" = "` through unless the drivers also trim before emitting; keep validation and emission consistent.
  - `assertValidIdentifier()` must accept BOTH the plain (`IDENTIFIER_PATTERN`) and qualified (`QUALIFIED_PATTERN`, i.e. `table.column`) forms, and reject everything else (backticks, double-quotes, `;`, `--`, `/* */`, whitespace, `->` JSON arrows). JSON-path columns are NOT validated here — the drivers route those to `compileJsonExtract()` before reaching identifier validation (see tasks 002/003).

## Requirements (Test Descriptions)
- [x] `it exposes the full comparison operator allowlist (=, !=, <>, <, >, <=, >=, LIKE, NOT LIKE, IN, NOT IN, IS, IS NOT)`
- [x] `it accepts every allowlisted operator via assertValidOperator without throwing`
- [x] `it rejects an operator not in the allowlist (e.g. "; DROP TABLE") via assertValidOperator`
- [x] `it rejects a lowercase-only keyword operator that does not match the allowlist casing`
- [x] `it rejects an allowlisted operator with surrounding whitespace or trailing SQL (e.g. "= OR 1=1")`
- [x] `it accepts a plain identifier via assertValidIdentifier`
- [x] `it accepts a qualified table.column identifier via assertValidIdentifier`
- [x] `it rejects an identifier containing a backtick, semicolon, or comment marker via assertValidIdentifier`
- [x] `it escapes an embedded backtick by doubling it via escapeDelimiter`
- [x] `it escapes an embedded double-quote by doubling it via escapeDelimiter`
- [x] `it throws InvalidColumnException with a helpful suggestion when an operator is rejected`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `public const array OPERATORS` to `IdentifierValidator` with 13 comparison operators
- Added `public static assertValidIdentifier(string $identifier): void` — accepts plain and qualified (`table.column`) forms via existing IDENTIFIER_PATTERN and QUALIFIED_PATTERN constants; rejects everything else
- Added `public static assertValidOperator(string $operator): void` — exact-match via `in_array(..., true)`; rejects lowercase variants, padded variants, and injection payloads
- Added `public static escapeDelimiter(string $identifier, string $delimiter): string` — doubles the delimiter character for safe embedding
- Added `InvalidColumnException::invalidOperator(string $operator): self` factory — references `IdentifierValidator::OPERATORS` to build the suggestion list (circular reference is safe in PHP for constants)
- All three methods are `public static` per the cross-task contract (tasks 002/003 call them statically)
- Several tests passed immediately without needing implementation changes because the underlying logic was already correct from the existing patterns (regex allowlist); implementation was added anyway to satisfy the contract
