# Task 002: Method and Path Filtering

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Add `--method` and `--path` filter options to `RouteListCommand`. Method filter is case-insensitive and uses the existing `RouteCollection::byMethod()`. **Important:** `strtoupper()` must be applied to the `--method` option value BEFORE passing to `byMethod()`, because `byMethod()` uses strict `===` comparison and routes are stored with uppercase methods ('GET', 'POST', etc.). Path filter uses `str_contains()` substring matching. Leading slash is stripped only from the filter value (route paths always start with `/`). Filters can be combined with AND logic. Shows a helpful message when filters match nothing.

## Context
- Related files:
  - `packages/routing/src/Commands/RouteListCommand.php` (modify)
  - `packages/routing/tests/Commands/RouteListCommandTest.php` (add tests)
  - `packages/core/src/Command/Input.php` (has `hasOption()`/`getOption()` for parsing `--method=GET`)
  - `packages/routing/src/RouteCollection.php` (has `byMethod()` already)
- Patterns to follow:
  - `Input::getOption('method')` returns the value after `=`, or `null` if not present
  - `Input::hasOption('method')` checks existence

## Requirements (Test Descriptions)
- [ ] `it filters routes by method when --method option is provided`
- [ ] `it filters routes by method case-insensitively`
- [ ] `it filters routes by path substring when --path option is provided`
- [ ] `it strips leading slash from path filter value before matching (route paths always start with /)`
- [ ] `it combines method and path filters`
- [ ] `it displays No routes match the given filters when filters match nothing`
- [ ] `it shows all routes when no filters are provided`

## Acceptance Criteria
- All requirements have passing tests
- Filtering works with both `--method=GET` and `--path=users` syntax
- Filters compose cleanly (AND logic)
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
