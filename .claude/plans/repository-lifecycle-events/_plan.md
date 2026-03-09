# Plan: Repository Lifecycle Events & Constructor Cleanup

## Created
2026-03-09

## Status
completed

## Objective
Eliminate repository constructor boilerplate by adding optional EventDispatcherInterface to the base Repository, adding standardized entity lifecycle events, extracting misplaced threading logic from CommentRepository, and cleaning up all 9 repository constructor overrides.

## Scope

### In Scope
- Add optional `EventDispatcherInterface` to base `Repository` constructor
- Create 6 standardized lifecycle event classes in `marko/database`: `EntityCreating`, `EntityCreated`, `EntityUpdating`, `EntityUpdated`, `EntityDeleting`, `EntityDeleted`
- Dispatch lifecycle events automatically from base `Repository::save()` and `Repository::delete()`
- Remove constructor overrides from all repositories that only existed to inject `EventDispatcherInterface`
- Extract `CommentRepository::buildTree()` / threading logic into `CommentThreadingService`
- Remove `getThreadedCommentsForPost()` from `CommentRepositoryInterface` (move to `CommentThreadingServiceInterface`)
- Remove `TokenRepository`'s completely pointless constructor and pass-through `save()`/`delete()` overrides
- Update all existing tests to match new structure
- Keep all existing module-specific events (PostCreated, CommentCreated, etc.) — repos that dispatch custom events still override `save()` but no longer need constructor overrides

### Out of Scope
- MarkoTalk's `MessageRepository` (consumer, not framework)
- Removing module-specific events in favor of generic ones (they carry domain-specific data)
- Event cancellation / preventing save (keep simple — notification events only)
- Adding `marko/database` dependency on `marko/core` for EventDispatcherInterface (already exists in composer.json)

## Success Criteria
- [ ] Zero repository constructor overrides exist solely for EventDispatcherInterface injection
- [ ] Base Repository dispatches lifecycle events when EventDispatcher is provided
- [ ] CommentRepository has no BlogConfigInterface dependency
- [ ] CommentThreadingService exists with proper interface
- [ ] TokenRepository has no constructor override
- [ ] All existing tests pass
- [ ] No behavioral changes to existing event dispatch (module-specific events still fire)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add EventDispatcherInterface to base Repository + lifecycle event classes | - | pending |
| 002 | Clean up TokenRepository (remove pointless constructor + pass-throughs) | 001 | pending |
| 003 | Clean up AuthorRepository constructor | 001 | pending |
| 004 | Clean up PostRepository constructor | 001 | pending |
| 005 | Clean up AdminUserRepository + RoleRepository constructors | 001 | pending |
| 006 | Clean up PermissionRepository constructor | 001 | pending |
| 007 | Extract CommentThreadingService from CommentRepository | 001 | pending |
| 008 | Clean up CommentRepository constructor (remove BlogConfigInterface + EventDispatcher) | 007 | pending |
| 009 | Clean up CategoryRepository constructor | 001 | pending |
| 010 | Clean up TagRepository constructor | 001 | pending |
| 011 | Update blog module.php bindings for CommentThreadingService | 007 | pending |

## Architecture Notes

### Lifecycle Events Design
- Events live in `packages/database/src/Events/` (new directory)
- Each event extends `Marko\Core\Event\Event`
- Each event carries: `Entity $entity`, `string $entityClass`
- Dispatched ONLY when `$this->eventDispatcher !== null`
- `EntityCreating`/`EntityUpdating`/`EntityDeleting` dispatch BEFORE the operation
- `EntityCreated`/`EntityUpdated`/`EntityDeleted` dispatch AFTER the operation

### Double Dispatch (Lifecycle + Domain Events)
When child repositories override `save()` and call `parent::save()`, BOTH lifecycle events AND domain-specific events fire. This is intentional:
1. `parent::save()` dispatches `EntityCreating` -> performs insert/update -> dispatches `EntityCreated`
2. Child `save()` then dispatches domain event (e.g., `PostCreated`)

Observers can listen to `EntityCreated` for cross-cutting concerns (audit logging, cache invalidation) while domain-specific observers listen to `PostCreated`, `AuthorCreated`, etc.

### Repository Pattern After Cleanup
- **No constructor needed**: TokenRepository, AuthorRepository, PostRepository, AdminUserRepository, RoleRepository, PermissionRepository, CommentRepository
- **Constructor still needed**: CategoryRepository (SlugGeneratorInterface), TagRepository (SlugGeneratorInterface) — EventDispatcherInterface is forwarded to parent, not stored privately. Note: TagRepository currently does NOT pass queryBuilderFactory to parent -- this must be fixed during cleanup.
- **save() override still needed**: PostRepository (status change events), CommentRepository (CommentCreated with post context), CategoryRepository (slug generation + custom events), TagRepository (slug generation + custom events), AuthorRepository (custom AuthorCreated/Updated events with timestamp), AdminUserRepository (custom AdminUserCreated/Updated events), RoleRepository (custom RoleCreated/Updated/Deleted events)
- **save() override NOT needed**: TokenRepository (was pass-through)

### CommentThreadingService
- New interface `CommentThreadingServiceInterface` with `getThreadedComments(int $postId): array`
- Implementation takes `CommentRepositoryInterface` + `BlogConfigInterface`
- `getThreadedCommentsForPost()` removed from `CommentRepositoryInterface`
- `PostController` and other callers switch to `CommentThreadingServiceInterface`

### Dependency Consideration
- `marko/database` already requires `marko/core` for `EventDispatcherInterface` (verified in composer.json)
- The DI container must handle the optional `?EventDispatcherInterface $eventDispatcher = null` parameter: if `EventDispatcherInterface` is not bound, the container should pass `null` (not throw). Both `blog/module.php` and `admin-auth/module.php` use simple class bindings, so automatic constructor injection must resolve this correctly.

## Risks & Mitigations
- **Test breakage from CommentRepositoryInterface change**: Many mocks implement `getThreadedCommentsForPost()` -- task 007 must update all mocks and callers
- **Dependency already satisfied**: `marko/database` already requires `marko/core` in composer.json
- **Constructor signature change in base Repository**: Any external code (like MarkoTalk) creating repositories directly will need to update -- acceptable since it's additive (new optional param)
- **Property shadowing during transition**: After task 001, child repos with `private $eventDispatcher` shadow the base class's `protected $eventDispatcher`. The base lifecycle events won't fire for those repos until their constructors are cleaned up. Tasks 002-010 must all complete for full functionality.
- **PermissionRepositoryInterface change**: Task 006 changes `syncFromRegistry()` signature -- must update interface, implementation, and all callers/tests
- **Blog README**: Task 007 must update `packages/blog/README.md` to remove `getThreadedCommentsForPost()` and document CommentThreadingServiceInterface
