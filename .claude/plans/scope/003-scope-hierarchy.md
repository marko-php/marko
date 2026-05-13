# Task 003: `ScopeHierarchy` (per-axis tree)

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Per-axis tree of scope paths. Supports building from a nested config array, walking from any path up to the root, checking path existence, and ancestor relationships. Materialized-path scheme — paths like `eu.de` imply `eu` is an ancestor.

## Context
- Related files: `packages/scope/src/Hierarchy/ScopeHierarchy.php` (new)
- Patterns to follow: Readonly value-object style. Pure-PHP — no DB, no I/O.

## Requirements (Test Descriptions)
- [x] `it builds a hierarchy from a nested array of paths`
- [x] `it walks up from a deep path to root returning the path and all ancestors in order`
- [x] `it returns true from exists for known paths and false for unknown`
- [x] `it identifies parent-child relationships via isAncestor`
- [x] `it throws UnknownScopeException when walking from an unknown path`
- [x] `it rejects duplicate path declarations at construction time`
- [x] `it lists all paths in declaration order`

## Acceptance Criteria
- Hierarchy is immutable after construction.
- `walkUp('eu.de')` returns `['eu.de', 'eu']` (excludes synthetic root — root path is implicit, represented as empty in the walk or as a special "all" marker; finalize during impl).
- O(1) `exists()` lookup via internal map; O(depth) `walkUp()`.

## Implementation Notes
- `ScopeHierarchy` uses a flat list of dotted paths (`['eu', 'eu.de', 'eu.fr']`) as the canonical input format via `fromPaths()`.
- Internal `$pathMap` (array<string, bool>) enables O(1) `exists()` lookups.
- `walkUp()` uses `strrpos('.', ...)` to traverse upward in O(depth) time.
- `isAncestor()` uses `str_starts_with($descendant, $ancestor . '.')` — pure string check, no hierarchy state needed.
- Duplicate detection in constructor throws `ScopeConfigurationException::duplicatePath()` (added `duplicatePath` factory method to that exception).
- The constructor accepts `array $paths = []` for backward compatibility with the `ScopeAxisTest` that calls `new ScopeHierarchy()`.
- `UnknownScopeException` was already created by another task; a minimal stub was not needed.
