# Task 027: `marko/scope-mysql` README

**Status**: complete
**Depends on**: 018, 019, 020, 021, 030
**Retry count**: 0

## Description
Write the `marko/scope-mysql` README per `.claude/code-standards.md` § Package README Standards. Implementation-package format: state what it does, explain concrete benefit, show it works automatically once installed.

## Context
- Related files: `packages/scope-mysql/README.md` (new)
- Patterns to follow: `packages/database-mysql/README.md` for driver-package style.

## Requirements (Test Descriptions)
- [x] `it has Title + One-Liner section stating the driver provides MySQL/MariaDB JSON support for marko/scope`
- [x] `it has Overview section of 2-4 sentences explaining ORDER BY emission and auto-migration via the existing extender pipeline`
- [x] `it has Installation section with composer command`
- [x] `it has Usage section noting the scopes column is added automatically by MigrationGenerator when a ScopedOverridesEntity extender is registered — no migration helper needed`
- [x] `it has Usage section noting ORDER BY support is automatic via ScopedOrderBy on Repository::matching`
- [x] `it has API Reference listing MySqlScopeSortRenderer`
- [x] `it notes MariaDB 10.3+ compatibility for JSON_EXTRACT and JSON_UNQUOTE`

## Acceptance Criteria
- README exists at `packages/scope-mysql/README.md`.
- Examples follow code standards.
- Notes `marko/scope` is pulled in transitively.
- No "migration helper" usage block — explains the auto-migration flow instead, with the one-line `ProductScopedOverrides` extender as the trigger.

## Implementation Notes
- Created `packages/scope-mysql/README.md` with all required sections.
- Created `packages/scope-mysql/tests/Unit/ReadmeTest.php` with 7 tests asserting README content via `->toContain()`.
- README includes: `ProductScopedOverrides extends ScopedOverridesEntity` extender example and `$repo->matching($factory->create(Product::class, 'name'))` ORDER BY example.
- Notes `marko/scope` is pulled in transitively; no migration helper block present.
