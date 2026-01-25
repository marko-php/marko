# Task 022: Category Lifecycle Events

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create events for category entity CRUD operations. Enables observers to react to category changes (menu rebuilding, cache invalidation, etc.).

## Context
- Related files: `packages/blog/src/Events/Category/` directory
- Patterns to follow: Same pattern as other lifecycle events
- Events dispatched from CategoryRepository

## Requirements (Test Descriptions)
- [ ] `it dispatches CategoryCreated event when category is created`
- [ ] `it dispatches CategoryUpdated event when category is modified`
- [ ] `it dispatches CategoryDeleted event when category is removed`
- [ ] `it includes full category entity in event data`
- [ ] `it includes parent category in event data if exists`
- [ ] `it includes timestamp in all events`

## Acceptance Criteria
- All requirements have passing tests
- Event classes created: CategoryCreated, CategoryUpdated, CategoryDeleted
- Events contain all useful data for observers
- CategoryRepository dispatches events appropriately
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
