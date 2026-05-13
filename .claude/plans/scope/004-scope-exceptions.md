# Task 004: Scope exception family

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Define loud-error exceptions for scope misconfiguration, missing axes, missing paths, missing context, and storage errors. All extend `MarkoException` with named `message`/`context`/`suggestion` parameters per the framework's loud-error standard.

## Context
- Related files: `packages/scope/src/Exceptions/*.php` (new)
- Patterns to follow: `packages/database/src/Exceptions/*.php`, `.claude/code-standards.md` § Exception Standards. Use static factory methods.

## Requirements (Test Descriptions)
- [x] `it provides UnknownAxisException with axis name and suggestion to register it`
- [x] `it provides UnknownScopeException with axis and path and suggestion`
- [x] `it provides ScopeConfigurationException for malformed scope config`
- [x] `it provides ScopeContextException when reading context for an unset axis or invalid path`
- [x] `it provides ScopeStorageException when the scopes column is missing on save`
- [x] `it extends MarkoException for all scope exceptions`

## Acceptance Criteria
- Each exception has at least one static factory method covering the most common case.
- Messages include actionable variable values; suggestions guide toward resolution.
- All exceptions registered in `Marko\Scope\Exceptions\` namespace.

## Implementation Notes
- Created five exception classes in `packages/scope/src/Exceptions/`:
  - `UnknownAxisException` — `forAxis(string $axis)` factory
  - `UnknownScopeException` — `forAxisAndPath(string $axis, string $path)` factory
  - `ScopeConfigurationException` — `malformedConfig(string $axis, string $reason)` and `duplicatePath(string $path)` factories
  - `ScopeContextException` — `axisNotSet(string $axis)` and `invalidPath(string $axis, string $path)` factories
  - `ScopeStorageException` — `missingColumn(string $column, string $table)` factory
- All extend `MarkoException` with named `message`/`context`/`suggestion` parameters
- Tests in `packages/scope/tests/Unit/Exceptions/ScopeExceptionsTest.php`
