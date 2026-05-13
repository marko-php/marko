# Task 002: `Scope` and `ScopeAxis` value objects

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Define the core value objects representing a single scope (`axis:path` tuple) and an axis (named tree of scope paths). Both are immutable readonly classes.

## Context
- Related files: `packages/scope/src/Axis/ScopeAxis.php` (new), `packages/scope/src/Scope.php` (new)
- Patterns to follow: Existing readonly value objects in `packages/database/src/Schema/Column.php`. Use `readonly class` since all properties are immutable.

## Requirements (Test Descriptions)
- [x] `it creates a ScopeAxis with name and hierarchy reference`
- [x] `it creates a Scope with axis name and path`
- [x] `it parses a scope string "geo:eu.de" via Scope::fromString`
- [x] `it throws ScopeConfigurationException for malformed scope strings`
- [x] `it formats a Scope back to "axis:path" via Scope::toString`
- [x] `it considers two Scope instances equal when axis and path match`

## Acceptance Criteria
- `ScopeAxis` is a `readonly class` with name (`string`) and hierarchy (`ScopeHierarchy`) — the hierarchy reference is forward-declared; the class lives in task 003.
- `Scope` is a `readonly class` with `axisName` and `path` accessible via `public private(set)` or getters.
- Round-trip: `Scope::fromString($s)->toString() === $s` for valid inputs.
- Both classes have `@throws` tags where relevant.

## Implementation Notes
- Created `packages/scope/src/Scope.php` as a `readonly class` with `axisName` and `path` public properties, plus `fromString()` (static factory), `toString()`, and `equals()` methods.
- Created `packages/scope/src/Axis/ScopeAxis.php` as a `readonly class` with `name` and `hierarchy` public properties.
- Created `packages/scope/src/Hierarchy/ScopeHierarchy.php` as a minimal stub (with default empty constructor) for use by `ScopeAxis`; task 003 will flesh this out.
- Created `packages/scope/src/Exception/ScopeConfigurationException.php` as a minimal stub extending `RuntimeException`; task 004 will flesh this out.
- `Scope::fromString()` has `@throws ScopeConfigurationException` tag.
- Round-trip verified: `Scope::fromString($s)->toString() === $s` for valid inputs.
- Requirement 4 passed immediately because the exception-throwing logic was required to implement requirement 3 (both handle `fromString` behavior).
- Ran php-cs-fixer on all created files; linter made minor formatting adjustments (empty constructor braces).
