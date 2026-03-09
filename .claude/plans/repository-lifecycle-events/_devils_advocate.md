# Devil's Advocate Review: repository-lifecycle-events

## Critical (Must fix before building)

### C1. Double event dispatch -- lifecycle events fire INSIDE domain event dispatch (Tasks 003, 004, 005, 008, 009, 010)

After task 001, `Repository::save()` dispatches `EntityCreating`/`EntityCreated` (or `EntityUpdating`/`EntityUpdated`). Every child repository that overrides `save()` calls `parent::save($entity)`, which means:

- `PostRepository::save()` calls `parent::save()` -> dispatches `EntityCreating` + `EntityCreated` -> then `PostRepository` dispatches `PostCreated`
- Same pattern for Author, Comment, Category, Tag, AdminUser, Role

This is not necessarily a bug -- it may be intentional that both generic and domain-specific events fire. But the plan never explicitly addresses this. If it IS intentional, the task descriptions and tests need to account for it: every `save()` test must verify that BOTH the lifecycle event AND the domain event are dispatched, in the correct order (lifecycle before/after wrapping the domain event).

If it is NOT intentional and only domain-specific events should fire when overridden, the base `save()` needs a way to skip lifecycle events (e.g., extract `insert`/`update` calls to separate methods that child repos call instead of `parent::save()`).

**Impact**: Every cleanup task (003-010) will produce unexpected event dispatch behavior if this is not clarified. Tests will either fail or be wrong.

**Fix**: Add explicit documentation to task 001 that lifecycle events ALWAYS fire when a dispatcher is present, even when child repos also dispatch domain events. Update all cleanup task tests to verify the double dispatch.

### C2. Property name collision -- `$this->eventDispatcher` is `private` in child repos, becoming `protected` in base (Tasks 003, 004, 005, 008, 009, 010)

PostRepository (line 37), AuthorRepository (line 33), AdminUserRepository (line 32), RoleRepository (line 34), CommentRepository (line 33) all declare:
```php
private readonly ?EventDispatcherInterface $eventDispatcher = null;
```

When task 001 adds `protected readonly ?EventDispatcherInterface $eventDispatcher = null` to the base `Repository`, and tasks 003-010 remove the child constructor overrides, the child repos' `save()`/`delete()` overrides reference `$this->eventDispatcher`. This will work because they will inherit the base class property. However, **during the transition** (before the child constructor is removed), having both a `private` child property AND a `protected` parent property with the same name will cause PHP to maintain TWO separate properties -- the child's private one shadows the parent's protected one.

This means task 001 CANNOT be deployed independently. If task 001 lands but task 003 (AuthorRepository) has not yet run, `AuthorRepository` will have its own `private $eventDispatcher` (set by its constructor) AND the base class will have a separate `protected $eventDispatcher` (set to `null` because the child constructor only passes 4 params to `parent::__construct()`). The base class lifecycle events will never fire for AuthorRepository because the base class's `$eventDispatcher` is `null`.

This is acceptable as an intermediate state IF all tasks are applied together, but the plan should explicitly note this.

**Impact**: Tasks 002-010 running in parallel against task 001's output could produce confusing test failures if a worker tests lifecycle event dispatch on a repo whose constructor hasn't been cleaned up yet.

### C3. TagRepository does NOT pass `queryBuilderFactory` to parent (Task 010)

Looking at `TagRepository::__construct()` (line 36):
```php
parent::__construct($connection, $metadataFactory, $hydrator);
```

It passes only 3 args to the parent, omitting `$queryBuilderFactory`. After task 001 adds `$eventDispatcher` as a 5th parameter, the base constructor signature becomes:
```
(ConnectionInterface, EntityMetadataFactory, EntityHydrator, ?Closure = null, ?EventDispatcherInterface = null)
```

Task 010 says "TagRepository constructor only has SlugGeneratorInterface" -- but the new constructor must ALSO pass `$eventDispatcher` to the parent AND handle `$queryBuilderFactory`. The current TagRepository doesn't even have `$queryBuilderFactory` as a constructor parameter (it's never passed to parent). The task description needs to account for this: the new constructor needs to accept and forward both `queryBuilderFactory` and `eventDispatcher` to the parent, or use a different approach.

Actually, re-reading more carefully: the plan says "remove EventDispatcherInterface from constructor" and keep only SlugGeneratorInterface. But the constructor ALSO needs to forward `$queryBuilderFactory` and `$eventDispatcher` to the parent. Task 010's description says the constructor reduces to "only SlugGeneratorInterface" which is wrong -- it must forward all parent params plus add SlugGeneratorInterface.

**Impact**: Task 010 worker will build a broken constructor.

### C4. CategoryRepository has same queryBuilderFactory ordering issue (Task 009)

CategoryRepository constructor (line 30-38) currently has:
```php
ConnectionInterface $connection,
EntityMetadataFactory $metadataFactory,
EntityHydrator $hydrator,
SlugGeneratorInterface $slugGenerator,
?Closure $queryBuilderFactory = null,
?EventDispatcherInterface $eventDispatcher = null,
```

Task 009 says "constructor only has SlugGeneratorInterface" and "reduces from 6 params to 4 (3 parent pass-throughs + SlugGeneratorInterface)". But after task 001, the parent has 5 params. The child constructor must accept and forward `queryBuilderFactory` AND `eventDispatcher` to the parent, plus keep `slugGenerator`. That's at minimum:

```php
public function __construct(
    ConnectionInterface $connection,
    EntityMetadataFactory $metadataFactory,
    EntityHydrator $hydrator,
    private readonly SlugGeneratorInterface $slugGenerator,
    ?Closure $queryBuilderFactory = null,
    ?EventDispatcherInterface $eventDispatcher = null,
) {
    parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory, $eventDispatcher);
}
```

The task description is misleading. The constructor still has 6 params -- only now `$eventDispatcher` is forwarded to parent rather than stored privately.

**Impact**: Task 009 worker will produce incorrect implementation.

### C5. PermissionRepositoryInterface must change for task 006 (Task 006)

Task 006 changes `syncFromRegistry()` to accept `PermissionRegistryInterface` as a parameter. But `PermissionRepositoryInterface` (line 37) defines `public function syncFromRegistry(): void`. Changing the method signature requires updating the interface too.

The task mentions "Update any callers of `syncFromRegistry()`" but does not mention updating `PermissionRepositoryInterface`. A worker building this task will hit a fatal error: the implementation won't match the interface contract.

Additionally, existing tests at `packages/admin-auth/tests/Unit/Repository/PermissionRepositoryInterfaceTest.php` verify the `syncFromRegistry` signature takes zero parameters. Those tests must also be updated.

**Impact**: Build failure without the interface update. Test failures without test updates.

## Important (Should fix before building)

### I1. Task 007 is too large for a single worker (Task 007)

Task 007 must:
1. Create `CommentThreadingServiceInterface` and `CommentThreadingService`
2. Move `buildTree()`, `calculateDepthFromMap()`, `findEffectiveParent()` from CommentRepository
3. Remove `getThreadedCommentsForPost()` from `CommentRepositoryInterface`
4. Remove `getThreadedCommentsForPost()` from `CommentRepository`
5. Update `PostController` constructor and `show()` method
6. Update 7+ mock files across the test suite (listed in the task)
7. Move threading tests from `CommentRepositoryTest.php` to new test file

That is at least 10+ files touched, including deeply embedded anonymous class mocks in test files. This is a high-risk, high-effort task. Consider splitting into:
- 007a: Create CommentThreadingService + interface + tests
- 007b: Remove from CommentRepositoryInterface + update all mocks/callers

### I2. CommentController also calls `calculateDepth()` -- not just PostController (Task 007)

Task 007 lists `PostController.php:104` as the only caller of `getThreadedCommentsForPost()`. The task does NOT mention `CommentController.php:187` which calls `$this->commentRepository->calculateDepth($parentId)`. While `calculateDepth()` stays on the interface, the task should verify `CommentController` doesn't also call `getThreadedCommentsForPost()` somewhere. I checked: it doesn't. But the task's "Caller Updates" section only mentions PostController -- any test mocks for CommentController that implement the old interface will need updating too (they already appear in the mock list, but it's worth being explicit).

### I3. `CommentRepository::calculateDepth()` uses N+1 queries (pre-existing, not introduced)

The `calculateDepth()` method (CommentRepository line 355-374) walks up the parent chain with individual `find()` calls. This is a pre-existing O(n) query issue per comment depth calculation. Not introduced by this plan, but the CommentThreadingService extraction is an opportunity to address it. Consider noting this as a future improvement in task 007.

### I4. Missing admin-auth module.php updates (Task 005, 006)

Task 011 updates `blog/module.php` for CommentThreadingService. But tasks 005 and 006 change AdminUserRepository, RoleRepository, and PermissionRepository constructor signatures. If the DI container uses automatic constructor injection, this may work. But if `admin-auth/module.php` has explicit factory closures for any of these repositories, those factories need updating too.

Looking at `admin-auth/module.php`, it uses simple class bindings (e.g., `AdminUserRepositoryInterface::class => AdminUserRepository::class`), not factories. So automatic injection should handle the new base class parameter. However, the DI container must be able to resolve `EventDispatcherInterface` or accept `null` for the optional parameter. If the container doesn't have `EventDispatcherInterface` bound, will it pass `null` or throw?

This depends on the container's behavior with optional parameters. The plan should explicitly note this assumption.

### I5. `CommentRepository` parameter ordering mismatch with parent (Task 008)

CommentRepository's current constructor puts `BlogConfigInterface` as the 4th parameter (before `?Closure $queryBuilderFactory`). This means the current call is:
```php
parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory);
```
where `$queryBuilderFactory` is the 5th constructor parameter but passed as the 4th parent argument. This works because the parent's 4th param is `?Closure $queryBuilderFactory`.

After task 007 removes `BlogConfigInterface`, task 008 says "remove constructor override entirely." But if the constructor is removed, the DI container must inject `EventDispatcherInterface` as the 5th parameter. The task should verify that CommentRepository doesn't need any special parameter ordering for DI resolution.

### I6. Blog README.md references `getThreadedCommentsForPost` (Task 007)

`packages/blog/README.md:287` documents `getThreadedCommentsForPost()` in the API reference. Task 007 should update the README to remove this method and add CommentThreadingServiceInterface documentation.

## Minor (Nice to address)

### M1. Event class structure could use a shared abstract base

All 6 lifecycle events (`EntityCreating`, `EntityCreated`, etc.) share the same `$entity` + `$entityClass` properties. Consider an abstract `EntityLifecycleEvent` base class to reduce duplication. Not blocking, just cleaner.

### M2. `out of scope` note about `marko/database` requiring `marko/core` is already resolved

The plan's "Out of Scope" section says "Adding `marko/database` dependency on `marko/core` for EventDispatcherInterface (need to verify current deps)" but then task 001's context says "marko/database already requires marko/core in composer.json." The out-of-scope item is misleading -- remove it to avoid confusion.

### M3. TokenRepository test requirements are weak (Task 002)

Task 002's test descriptions include "it saves a verification token without constructor override" and "it deletes a verification token without method override." These are vague -- what exactly is being tested? The absence of a constructor override is a structural assertion, not a behavioral one. Better: test that save/delete work correctly (behavioral) and add a structural test that TokenRepository does not override the constructor (using reflection).

## Questions for the Team

### Q1. Is double event dispatch (lifecycle + domain) intentional?

When `PostRepository::save()` calls `parent::save()`, the base class will now dispatch `EntityCreating`/`EntityCreated`, and then PostRepository dispatches `PostCreated`. Is this intended behavior? Should observers that want "any entity saved" listen to `EntityCreated`, while observers that want "a post was created specifically" listen to `PostCreated`? This needs an explicit architectural decision.

### Q2. Should `calculateDepth()` move to CommentThreadingService too?

`calculateDepth()` is currently on `CommentRepositoryInterface` and called by `CommentController`. It's conceptually a threading/depth concern. Should it stay on the repository (it does N+1 queries walking up parents) or move to the threading service where it could use the in-memory tree?

### Q3. Container behavior with optional parameters

When the DI container resolves a repository and `EventDispatcherInterface` is not bound, does it pass `null` for the optional 5th parameter or throw? This determines whether lifecycle events "just work" or require explicit configuration.
