# Task 008: Clean Up CommentRepository Constructor

**Status**: complete
**Depends on**: 007
**Retry count**: 0

## Description
Remove CommentRepository's constructor override. After task 007 extracts the threading logic, BlogConfigInterface is gone. EventDispatcherInterface is now on the base class. The `save()` and `delete()` overrides stay for domain events (CommentCreated, CommentDeleted).

## Context
- Related files: `packages/blog/src/Repositories/CommentRepository.php`
- After task 007, the constructor only has EventDispatcherInterface (which is now on base)
- `save()` dispatches CommentCreated with post context — must stay
- `delete()` dispatches CommentDeleted with post context — must stay
- Both use `$this->eventDispatcher` which is now inherited from base
- The property was `private readonly` — base class has it as `protected readonly`

## Requirements (Test Descriptions)
- [x] `it constructs without explicit EventDispatcherInterface or BlogConfigInterface`
- [x] `it dispatches lifecycle events and CommentCreated domain event with post on new comment save`
- [x] `it dispatches CommentDeleted event with post on comment delete`
- [x] `it finds verified comments for a post`
- [x] `it finds pending comments for a post`

Note: Both lifecycle events (from base Repository) AND domain events (CommentCreated) fire. See task 001.

## Acceptance Criteria
- All requirements have passing tests
- CommentRepository has no constructor override
- save()/delete() overrides remain (domain events with post context)
- Existing CommentRepository tests still pass
