# Task 004: Gate-Policy Integration

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Integrate policies into the Gate so that when checking abilities, the Gate first checks registered closures, then falls back to policy resolution based on the entity argument. This makes the Gate the single entry point for all authorization checks.

## Context
- Related files: `packages/authorization/src/Gate.php`, `packages/authorization/src/PolicyRegistry.php`
- Resolution order: explicit gate closure > entity policy > deny by default
- When the first argument after ability name is an object, Gate checks if a policy exists for that object's class
- `Gate::policy(entityClass, policyClass)` delegates to PolicyRegistry
- Patterns to follow: Single responsibility, Gate delegates to PolicyRegistry

## Requirements (Test Descriptions)
- [ ] `it delegates to policy when ability argument is an entity with registered policy`
- [ ] `it prefers gate closure over policy when both are defined`
- [ ] `it falls back to policy when no gate closure matches`
- [ ] `it denies by default when neither closure nor policy exists`
- [ ] `it registers policies via gate policy method`
- [ ] `it passes user and entity to policy method`
- [ ] `it handles authorize with entity policies throwing on denial`

## Acceptance Criteria
- All requirements have passing tests
- Gate is the unified entry point for all authorization
- Clean delegation to PolicyRegistry
- No circular dependencies

## Implementation Notes
(Left blank - filled in by programmer during implementation)
