# Task 006: Implement module walker in codeindexer

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Implement a `ModuleWalker` service that scans `vendor/`, `modules/`, and `app/` directories for `composer.json` files (following Marko's module discovery rules from `.claude/architecture.md`) and returns a list of discovered modules with their paths, names, and `module.php` metadata (when present).

## Context
- Reference: `.claude/architecture.md` § Module Discovery (vendor two-deep, modules recursive, app one-deep)
- Namespace: `Marko\CodeIndexer\Module\ModuleWalker`
- Contract: `Marko\CodeIndexer\Contracts\ModuleWalkerInterface`
- Return type: array of `ModuleInfo` value objects (readonly class with name, path, manifest array)

## Requirements (Test Descriptions)
- [ ] `it discovers vendor modules two levels deep`
- [ ] `it discovers app modules one level deep`
- [ ] `it discovers modules directory recursively`
- [ ] `it returns empty array when no modules exist`
- [ ] `it returns ModuleInfo with correctly parsed composer.json name`
- [ ] `it returns ModuleInfo with module.php manifest when file exists`
- [ ] `it returns ModuleInfo with empty manifest when module.php is absent`
- [ ] `it respects override priority app over modules over vendor when duplicates exist`

## Acceptance Criteria
- Pest fixtures under `tests/Fixtures/` simulate a mini monorepo
- All tests pass
- No I/O in constructors — walker is stateless

## Implementation Notes
(Filled in by programmer during implementation)
