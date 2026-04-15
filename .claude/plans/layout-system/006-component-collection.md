# Task 006: ComponentCollection

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Create the `ComponentCollection` class — an ordered collection of `ComponentDefinition` objects keyed by class name. Provides methods for adding, removing, moving, getting, and listing components. Supports sorting by slot, then sortOrder, respecting before/after constraints. Uses class references as identifiers (no string names).

## Context
- Related files: `packages/routing/src/RouteCollection.php` (pattern to follow)
- Components are keyed by their fully-qualified class name (class-string)
- `remove()` and `move()` accept class-string references
- `forSlot()` returns components filtered and sorted for a specific slot
- Throws `ComponentNotFoundException` when referencing a non-existent component
- Throws `DuplicateComponentException` when adding a component class that already exists
- **Important:** `ComponentDefinition` is `readonly`, so `move()` must use PHP 8.5 `clone() with` to create a new definition with the updated slot/sortOrder: `clone($definition) with { slot: $newSlot, sortOrder: $newSortOrder }`. Replace the old entry in the internal array with the cloned definition.

## Requirements (Test Descriptions)
- [ ] `it adds a component definition`
- [ ] `it throws DuplicateComponentException when adding same class twice`
- [ ] `it removes a component by class reference`
- [ ] `it throws ComponentNotFoundException when removing non-existent component`
- [ ] `it gets a component by class reference`
- [ ] `it throws ComponentNotFoundException when getting non-existent component`
- [ ] `it returns all component definitions`
- [ ] `it returns components filtered by slot`
- [ ] `it sorts components by sortOrder within a slot`
- [ ] `it respects before constraint over sortOrder when sorting`
- [ ] `it respects after constraint over sortOrder when sorting`
- [ ] `it throws AmbiguousSortOrderException when two components have same sortOrder with no before or after constraints`
- [ ] `it moves a component to a different slot`
- [ ] `it moves a component with a new sortOrder`
- [ ] `it returns count of components`
- [ ] `it returns components grouped by slot`

## Acceptance Criteria
- All requirements have passing tests
- Uses class-string references for all identifiers
- Throws loud errors for invalid operations
- Follows RouteCollection pattern
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
