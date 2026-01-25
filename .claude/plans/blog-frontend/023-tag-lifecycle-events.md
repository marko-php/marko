# Task 023: Tag Lifecycle Events

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Create events for tag entity CRUD operations. Enables observers to react to tag changes (tag cloud updates, cache invalidation, etc.).

## Context
- Related files: `packages/blog/src/Events/Tag/` directory
- Patterns to follow: Same pattern as other lifecycle events
- Events dispatched from TagRepository

## Requirements (Test Descriptions)
- [ ] `it dispatches TagCreated event when tag is created`
- [ ] `it dispatches TagUpdated event when tag is modified`
- [ ] `it dispatches TagDeleted event when tag is removed`
- [ ] `it includes full tag entity in event data`
- [ ] `it includes timestamp in all events`

## Acceptance Criteria
- All requirements have passing tests
- Event classes created: TagCreated, TagUpdated, TagDeleted
- Events contain all useful data for observers
- TagRepository dispatches events appropriately
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
