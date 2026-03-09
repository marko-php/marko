# Task 004: Clean Up PostRepository Constructor

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove PostRepository's constructor override. It only existed to inject EventDispatcherInterface, which is now on the base class. The `save()` and `delete()` overrides stay because they dispatch domain-specific events (PostCreated, PostUpdated, PostPublished, PostScheduled, PostDeleted).

## Context
- Related files: `packages/blog/src/Repositories/PostRepository.php`
- Constructor currently repeats 4 parent params + adds `EventDispatcherInterface`
- `save()` has complex logic: checks `isNew`, tracks `previousStatus` for status change events (PostPublished, PostScheduled) — must stay
- `delete()` dispatches PostDeleted — must stay
- After removing constructor, `$this->eventDispatcher` is inherited from base class
- The property was `private readonly` in PostRepository — will become `protected readonly` from base. Update `dispatchSaveEvent()`, `dispatchStatusChangeEvent()`, and `delete()` references accordingly
- Existing tests: `packages/blog/tests/Controllers/PostControllerTest.php`, `packages/blog/tests/Unit/Admin/*/PostAdmin*`

## Requirements (Test Descriptions)
- [ ] `it constructs without explicit EventDispatcherInterface parameter`
- [ ] `it dispatches lifecycle events and PostCreated domain event on new post save`
- [ ] `it dispatches lifecycle events and PostUpdated domain event on existing post save`
- [ ] `it dispatches PostPublished event on status change to published`
- [ ] `it dispatches PostScheduled event on status change to scheduled`
- [ ] `it dispatches PostDeleted event on delete`

Note: Both lifecycle events (EntityCreating/EntityCreated from base) AND domain events (PostCreated/PostUpdated) fire. Lifecycle events fire inside `parent::save()`. See task 001.

## Acceptance Criteria
- All requirements have passing tests
- PostRepository has no constructor
- save() and delete() overrides remain (they have domain logic)
- Existing PostRepository tests still pass
