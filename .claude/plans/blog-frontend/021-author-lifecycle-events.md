# Task 021: Author Lifecycle Events

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Create events for author entity CRUD operations. Enables observers to react to author changes (cache invalidation, search index updates, etc.).

## Context
- Related files: `packages/blog/src/Events/Author/` directory
- Patterns to follow: Same pattern as post and comment events
- Events dispatched from AuthorRepository

## Requirements (Test Descriptions)
- [ ] `it dispatches AuthorCreated event when author is created`
- [ ] `it dispatches AuthorUpdated event when author is modified`
- [ ] `it dispatches AuthorDeleted event when author is removed`
- [ ] `it includes full author entity in event data`
- [ ] `it includes timestamp in all events`

## Acceptance Criteria
- All requirements have passing tests
- Event classes created: AuthorCreated, AuthorUpdated, AuthorDeleted
- Events contain all useful data for observers
- AuthorRepository dispatches events appropriately
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
