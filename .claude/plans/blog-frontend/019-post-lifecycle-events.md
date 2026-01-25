# Task 019: Post Lifecycle Events

**Status**: pending
**Depends on**: 007
**Retry count**: 0

## Description
Create events dispatched throughout the post lifecycle. Events include full post data and context, enabling observers to react to post changes (logging, notifications, cache invalidation, etc.).

## Context
- Related files: `packages/blog/src/Events/Post/` directory
- Patterns to follow: Marko event system with `#[Observer]` attribute for listeners
- Events are dispatched from PostRepository and service layer

## Requirements (Test Descriptions)
- [ ] `it dispatches PostCreated event when post is saved first time`
- [ ] `it dispatches PostUpdated event when existing post is modified`
- [ ] `it dispatches PostPublished event when status changes to published`
- [ ] `it dispatches PostScheduled event when status changes to scheduled`
- [ ] `it dispatches PostDeleted event when post is removed`
- [ ] `it includes full post entity in event data`
- [ ] `it includes previous status in status change events`
- [ ] `it includes timestamp in all events`

## Acceptance Criteria
- All requirements have passing tests
- Event classes created: PostCreated, PostUpdated, PostPublished, PostScheduled, PostDeleted
- Events contain all useful data for observers
- Repository dispatches events at appropriate times
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
