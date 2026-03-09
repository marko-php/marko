# Task 010: Clean Up TagRepository Constructor

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove EventDispatcherInterface from TagRepository's constructor. Like CategoryRepository, it still needs a constructor for SlugGeneratorInterface, but EventDispatcherInterface is eliminated.

## Context
- Related files: `packages/blog/src/Repositories/TagRepository.php`
- Constructor currently has: ConnectionInterface, EntityMetadataFactory, EntityHydrator, SlugGeneratorInterface, ?EventDispatcherInterface
- **IMPORTANT**: The current constructor does NOT pass `queryBuilderFactory` to parent -- `parent::__construct($connection, $metadataFactory, $hydrator)` only passes 3 args
- After task 001, parent has 5 params: (ConnectionInterface, EntityMetadataFactory, EntityHydrator, ?Closure, ?EventDispatcherInterface)
- The new constructor must accept and forward ALL parent params plus SlugGeneratorInterface
- Note: TagRepository uses `protected readonly` for both slugGenerator and eventDispatcher -- the eventDispatcher one goes away (inherited from base)
- `save()` has slug generation + custom TagCreated/TagUpdated events -- must stay
- `delete()` has post count check + TagDeleted event -- must stay
- `$this->eventDispatcher` changes from `protected readonly` (local) to inherited `protected readonly` (base)
- Both lifecycle events (EntityCreating/EntityCreated) AND domain events (TagCreated/TagUpdated) will fire -- see task 001 notes

## Requirements (Test Descriptions)
- [ ] `it constructs with SlugGeneratorInterface plus parent params forwarded`
- [ ] `it auto-generates slug on save`
- [ ] `it dispatches TagCreated event on new tag`
- [ ] `it dispatches TagUpdated event on existing tag`
- [ ] `it dispatches TagDeleted event on delete`
- [ ] `it throws TagHasPostsException when deleting tag with posts`

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

Note: The current code omits `$queryBuilderFactory` when calling `parent::__construct()`. The new constructor must forward it.

## Acceptance Criteria
- All requirements have passing tests
- TagRepository constructor only promotes SlugGeneratorInterface (other params forwarded to parent)
- EventDispatcherInterface not stored as private property (forwarded to parent, inherited as protected)
- save()/delete() overrides remain
- Existing TagRepository tests still pass
