# Task 001: Add `extends:` param to `#[Table]` attribute

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Extend the existing `#[Table]` attribute with an optional `extends: ?class-string $extends = null` parameter and make `name:` optional. When `extends:` is set, the entity is an extender — its table name will be resolved later from the parent's `#[Table(name:)]`. Validation of "exactly one of name/extends" lives in the metadata factory (Task 004), not the attribute itself — the attribute just carries the values.

## Context
- Related files:
  - `packages/database/src/Attributes/Table.php` (modify)
  - `packages/database/tests/Attributes/` (add new test file `TableAttributeTest.php` if not present)
- Patterns to follow:
  - Existing `#[Preference(replaces:)]` uses a `class-string` parameter — match that style
  - Keep the attribute `readonly` and a plain DTO; no logic in the attribute class

## Requirements (Test Descriptions)
- [x] `it constructs with name only (no extends)`
- [x] `it constructs with extends only (no name)`
- [x] `it constructs with both name and extends set`
- [x] `it constructs with neither name nor extends (validation deferred to factory)`
- [x] `it exposes extends as nullable class-string property`
- [x] `it exposes name as nullable string property`

## Acceptance Criteria
- All requirements have passing tests
- `Table::$name` is `?string` (was `string`)
- New `Table::$extends` is `?string` defaulting to `null`
- Existing code that uses `new Table(name: 'foo')` continues to compile
- No behavior change to consumers that don't pass `extends:`
- Code follows code standards (php-cs-fixer, phpcs)
