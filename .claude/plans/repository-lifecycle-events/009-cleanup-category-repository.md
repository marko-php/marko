# Task 009: Clean Up CategoryRepository Constructor

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove the private `EventDispatcherInterface` property from CategoryRepository's constructor. It still needs a constructor for `SlugGeneratorInterface`, and must forward all parent params (including `eventDispatcher`) to the parent constructor instead of storing it privately.

## Context
- Related files: `packages/blog/src/Repositories/CategoryRepository.php`
- Constructor currently has: ConnectionInterface, EntityMetadataFactory, EntityHydrator, SlugGeneratorInterface, ?Closure, ?EventDispatcherInterface
- After cleanup: just SlugGeneratorInterface needs injection (parent handles the rest + eventDispatcher)
- `save()` has slug generation + custom CategoryCreated/CategoryUpdated events — must stay
- `delete()` has post/child checks + CategoryDeleted event — must stay
- `$this->eventDispatcher` changes from `private readonly` to inherited `protected readonly`
- `dispatchSaveEvent()` and `dispatchDeleteEvent()` reference `$this->eventDispatcher` — still works

## Requirements (Test Descriptions)
- [ ] `it constructs with SlugGeneratorInterface plus parent params forwarded`
- [ ] `it auto-generates slug on save`
- [ ] `it dispatches CategoryCreated event on new category`
- [ ] `it dispatches CategoryUpdated event on existing category`
- [ ] `it dispatches CategoryDeleted event on delete`
- [ ] `it prevents deletion of category with posts`
- [ ] `it prevents deletion of category with children`

## Implementation Notes

### Constructor After Cleanup
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

The constructor still has 6 params, but `$eventDispatcher` is now forwarded to parent instead of stored as a private property. `$this->eventDispatcher` in `dispatchSaveEvent()`/`dispatchDeleteEvent()` now references the inherited protected property from base.

Both lifecycle events (EntityCreating/EntityCreated) AND domain events (CategoryCreated/CategoryUpdated) will fire -- see task 001 notes.

## Acceptance Criteria
- All requirements have passing tests
- CategoryRepository constructor only promotes SlugGeneratorInterface (other params forwarded to parent)
- EventDispatcherInterface not stored as private property (forwarded to parent, inherited as protected)
- save()/delete() overrides remain
- Existing CategoryRepository tests still pass
