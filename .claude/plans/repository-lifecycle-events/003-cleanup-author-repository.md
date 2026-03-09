# Task 003: Clean Up AuthorRepository Constructor

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove AuthorRepository's constructor override. It only existed to inject EventDispatcherInterface, which is now on the base class. The `save()` override stays because it dispatches custom `AuthorCreated`/`AuthorUpdated` events with timestamps. The `delete()` override stays because it checks for associated posts and dispatches `AuthorDeleted`.

## Context
- Related files: `packages/blog/src/Repositories/AuthorRepository.php`
- Constructor currently repeats 4 parent params + adds `EventDispatcherInterface`
- `save()` dispatches `AuthorCreated`/`AuthorUpdated` — these are domain events with timestamps, keep them
- `delete()` checks for associated posts before deleting, dispatches `AuthorDeleted` — keep it
- After removing constructor, `$this->eventDispatcher` references in save()/delete() still work because it's now a protected property on the base class
- Existing tests: `packages/blog/tests/Controllers/AuthorControllerTest.php`, `packages/blog/tests/Unit/Admin/*/AuthorAdmin*`

## Requirements (Test Descriptions)
- [ ] `it constructs without explicit EventDispatcherInterface parameter`
- [ ] `it dispatches EntityCreating and EntityCreated lifecycle events via parent on new author save`
- [ ] `it dispatches AuthorCreated domain event on new author save`
- [ ] `it dispatches EntityUpdating and EntityUpdated lifecycle events via parent on existing author save`
- [ ] `it dispatches AuthorUpdated domain event on existing author save`
- [ ] `it dispatches AuthorDeleted event on delete`
- [ ] `it throws AuthorHasPostsException when deleting author with posts`

Note: Both lifecycle events (from base Repository) AND domain events (AuthorCreated/AuthorUpdated) fire when save() is called. The lifecycle events fire inside `parent::save()`, then the domain event fires after. See task 001 for the architectural decision.

## Acceptance Criteria
- All requirements have passing tests
- AuthorRepository has no constructor
- save() and delete() overrides remain (they have domain logic)
- Existing AuthorRepository tests still pass
