# Task 001: Add EventDispatcherInterface to Base Repository + Lifecycle Event Classes

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add an optional `EventDispatcherInterface` parameter to the base `Repository` constructor and create 6 standardized lifecycle event classes. The base `save()` and `delete()` methods dispatch these events automatically when a dispatcher is present.

## Context
- Related files: `packages/database/src/Repository/Repository.php`, `packages/database/src/Repository/RepositoryInterface.php`
- New directory: `packages/database/src/Events/`
- The `Event` base class is at `Marko\Core\Event\Event` — extend it
- `EventDispatcherInterface` is at `Marko\Core\Event\EventDispatcherInterface`
- `marko/database` already requires `marko/core` in composer.json
- Existing tests: `packages/database/tests/Repository/RepositoryTest.php`, `packages/database/tests/Feature/RepositoryCrudTest.php`
- The `RepositoryInterface` does NOT change — `save()` and `delete()` signatures stay the same

## Requirements (Test Descriptions)

### Event Classes
- [ ] `it creates EntityCreating event with entity and entity class`
- [ ] `it creates EntityCreated event with entity and entity class`
- [ ] `it creates EntityUpdating event with entity and entity class`
- [ ] `it creates EntityUpdated event with entity and entity class`
- [ ] `it creates EntityDeleting event with entity and entity class`
- [ ] `it creates EntityDeleted event with entity and entity class`
- [ ] `it creates abstract EntityLifecycleEvent base class with entity and entityClass properties`
- [ ] `it extends EntityLifecycleEvent for all 6 concrete lifecycle event classes`
- [ ] `it extends the base Event class via EntityLifecycleEvent for all lifecycle events`

### Base Repository Constructor
- [ ] `it accepts optional EventDispatcherInterface as fifth constructor parameter`
- [ ] `it works without EventDispatcherInterface (null by default)`

### Lifecycle Event Dispatch in save()
- [ ] `it dispatches EntityCreating before insert when dispatcher provided`
- [ ] `it dispatches EntityCreated after insert when dispatcher provided`
- [ ] `it dispatches EntityUpdating before update when dispatcher provided`
- [ ] `it dispatches EntityUpdated after update when dispatcher provided`
- [ ] `it does not dispatch events when no dispatcher is provided`

### Lifecycle Event Dispatch in delete()
- [ ] `it dispatches EntityDeleting before delete when dispatcher provided`
- [ ] `it dispatches EntityDeleted after delete when dispatcher provided`
- [ ] `it does not dispatch delete events when no dispatcher is provided`

## Implementation Notes

### Double Dispatch Behavior (IMPORTANT)
When child repositories override `save()` and call `parent::save()`, the base class lifecycle events WILL fire in addition to any domain-specific events the child dispatches. This is intentional:
- `EntityCreating` fires BEFORE the insert/update
- `EntityCreated` fires AFTER the insert/update
- Then the child repository dispatches its domain event (e.g., `PostCreated`)

This means observers can listen to generic lifecycle events (`EntityCreated`) for cross-cutting concerns, while domain-specific observers listen to `PostCreated`, `AuthorCreated`, etc.

Tests in this task must verify this behavior explicitly. Tests in cleanup tasks (003-010) must also verify that both lifecycle AND domain events fire.

### Abstract Base Event Class
Create an `EntityLifecycleEvent` abstract base class to reduce duplication across the 6 event classes:
```php
abstract class EntityLifecycleEvent extends Event {
    public function __construct(
        public readonly Entity $entity,
        public readonly string $entityClass,
    ) {}
}
```

Each concrete event simply extends it:
```php
class EntityCreated extends EntityLifecycleEvent {}
```

### Repository save() changes
```php
public function save(Entity $entity): void {
    $this->validateEntityType($entity);

    if ($this->hydrator->isNew($entity, $this->metadata)) {
        $this->eventDispatcher?->dispatch(new EntityCreating($entity, static::ENTITY_CLASS));
        $this->insert($entity);
        $this->eventDispatcher?->dispatch(new EntityCreated($entity, static::ENTITY_CLASS));
    } else {
        $this->eventDispatcher?->dispatch(new EntityUpdating($entity, static::ENTITY_CLASS));
        $this->update($entity);
        $this->eventDispatcher?->dispatch(new EntityUpdated($entity, static::ENTITY_CLASS));
    }
}
```

### Repository delete() changes
```php
public function delete(Entity $entity): void {
    $this->validateEntityType($entity);
    $this->eventDispatcher?->dispatch(new EntityDeleting($entity, static::ENTITY_CLASS));
    // ... existing delete logic ...
    $this->eventDispatcher?->dispatch(new EntityDeleted($entity, static::ENTITY_CLASS));
}
```

### Constructor change
Add `protected readonly ?EventDispatcherInterface $eventDispatcher = null` as the 5th parameter.

### Property Shadowing Warning
After this task lands, child repositories that still have `private readonly ?EventDispatcherInterface $eventDispatcher` will shadow the base class's `protected readonly ?EventDispatcherInterface $eventDispatcher`. The base class's property will be `null` (not passed by child constructors), so lifecycle events will NOT fire for those repositories until their constructors are cleaned up in tasks 003-010. This is an expected intermediate state.

## Acceptance Criteria
- All requirements have passing tests
- Existing Repository tests still pass
- No changes to RepositoryInterface
- Code follows code standards
