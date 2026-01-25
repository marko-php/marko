# Task 019: Post Lifecycle Events

**Status**: complete
**Depends on**: 007
**Retry count**: 0

## Description
Create events dispatched throughout the post lifecycle. Events include full post data and context, enabling observers to react to post changes (logging, notifications, cache invalidation, etc.).

## Context
- Related files: `packages/blog/src/Events/Post/` directory
- Patterns to follow: Marko event system with `#[Observer]` attribute for listeners
- Events are dispatched from PostRepository and service layer

## Requirements (Test Descriptions)
- [x] `it dispatches PostCreated event when post is saved first time`
- [x] `it dispatches PostUpdated event when existing post is modified`
- [x] `it dispatches PostPublished event when status changes to published`
- [x] `it dispatches PostScheduled event when status changes to scheduled`
- [x] `it dispatches PostDeleted event when post is removed`
- [x] `it includes full post entity in event data`
- [x] `it includes previous status in status change events`
- [x] `it includes timestamp in all events`

## Acceptance Criteria
- All requirements have passing tests
- Event classes created: PostCreated, PostUpdated, PostPublished, PostScheduled, PostDeleted
- Events contain all useful data for observers
- Repository dispatches events at appropriate times
- Code follows Marko standards

## Implementation Notes

**Files Created:**
- `packages/blog/src/Events/Post/PostCreated.php` - Event dispatched when a post is created
- `packages/blog/src/Events/Post/PostUpdated.php` - Event dispatched when an existing post is updated
- `packages/blog/src/Events/Post/PostPublished.php` - Event dispatched when post status changes to published (includes previousStatus)
- `packages/blog/src/Events/Post/PostScheduled.php` - Event dispatched when post status changes to scheduled (includes previousStatus)
- `packages/blog/src/Events/Post/PostDeleted.php` - Event dispatched when a post is deleted
- `packages/blog/tests/Events/Post/PostLifecycleEventsTest.php` - Test file with all 8 test cases

**Files Modified:**
- `packages/blog/src/Repositories/PostRepository.php` - Added eventDispatcher constructor parameter, overridden save() and delete() methods to dispatch events, added status tracking for change events

**Implementation Details:**
- Events use constructor property promotion with readonly properties
- All events include getPost() and getTimestamp() methods
- PostPublished and PostScheduled events also include getPreviousStatus()
- The repository tracks original status using the EntityHydrator's getOriginalValues() method
- Events are dispatched after the parent save/delete operation completes
- eventDispatcher is optional (nullable) to maintain backward compatibility
