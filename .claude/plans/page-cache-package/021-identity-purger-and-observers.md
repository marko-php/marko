# Task 021: `IdentityPurger` Service + Three Entity Lifecycle Observers

**Status**: pending
**Depends on**: 019, 020
**Retry count**: 0

## Description

Implement the `IdentityPurger` service (does the actual purge work) and three thin observer classes that delegate to it. The observers listen to `EntityCreated`, `EntityUpdated`, and `EntityDeleted` and, for any entity implementing `IdentityInterface`, purge each tag returned by `getIdentities()` from the page cache. Entities not implementing the interface are silently ignored — the observers are no-ops for them.

## Context

- Related files:
  - `packages/page-cache-entity/src/IdentityPurger.php` — new
  - `packages/page-cache-entity/src/Observer/PurgeOnEntityCreated.php` — new
  - `packages/page-cache-entity/src/Observer/PurgeOnEntityUpdated.php` — new
  - `packages/page-cache-entity/src/Observer/PurgeOnEntityDeleted.php` — new
  - `packages/database/src/Events/EntityCreated.php`, `EntityUpdated.php`, `EntityDeleted.php` — event classes to observe
  - `packages/page-cache/src/Contracts/PageCacheInterface.php` — `purgeTag(string $tag): bool`
  - `packages/page-cache/src/Contracts/IdentityInterface.php` — built in task 019
  - `packages/core/src/Attributes/Observer.php` — `#[Observer(event: SomeEvent::class)]`
- Pattern to follow: Other observer classes in the codebase use `#[Observer(event: ...)]` on the class and define a single `handle(EventClass $event): void` method. The dispatcher resolves observer classes via the container, so dependencies are constructor-injected.

**Why three observer classes, not one:**
`#[Observer]` is not `IS_REPEATABLE`, and `EventDispatcher::dispatch()` looks up observers by exact `$event::class` (no parent-class lookup). One class subscribes to exactly one event. The three thin observers all delegate to a single `IdentityPurger`, so business logic lives in one place.

**IdentityPurger shape:**
```php
namespace Marko\PageCache\Entity;

use Marko\Database\Entity\Entity;
use Marko\PageCache\Contracts\IdentityInterface;
use Marko\PageCache\Contracts\PageCacheInterface;

readonly class IdentityPurger
{
    public function __construct(
        private PageCacheInterface $pageCache,
    ) {}

    public function purge(Entity $entity): void
    {
        if (!$entity instanceof IdentityInterface) {
            return;
        }

        foreach ($entity->getIdentities() as $tag) {
            $this->pageCache->purgeTag($tag);
        }
    }
}
```

**Observer shape (one of three, others mirror exactly):**
```php
namespace Marko\PageCache\Entity\Observer;

use Marko\Core\Attributes\Observer;
use Marko\Database\Events\EntityUpdated;
use Marko\PageCache\Entity\IdentityPurger;

#[Observer(event: EntityUpdated::class)]
readonly class PurgeOnEntityUpdated
{
    public function __construct(
        private IdentityPurger $identityPurger,
    ) {}

    public function handle(EntityUpdated $event): void
    {
        $this->identityPurger->purge($event->entity);
    }
}
```

**`purgeTag()` return value is intentionally ignored** — purge is best-effort. A `false` return typically means an underlying I/O failure; a missing tag index is normal (FilePageCacheDriver returns `true` when the tag index file doesn't exist). Surfacing either case as an error would be too noisy.

**Tests should use a fake `PageCacheInterface`** that records `purgeTag()` invocations rather than touching the filesystem.

**Observer file placement is load-bearing for discovery.** `ObserverDiscovery::discover()` walks each module's `src/` directory and reads `#[Observer]` attributes off classes via `ClassFileParser`. The three observer files MUST live under `packages/page-cache-entity/src/Observer/` (matching the PSR-4 prefix `Marko\\PageCache\\Entity\\Observer\\`). Files placed outside `src/` will not be auto-registered, and the entire feature will silently no-op at runtime — the most important integration failure mode to guard against.

**Type contract note.** `IdentityInterface` (task 019) allows arbitrary classes to implement it, but `IdentityPurger::purge()` accepts `Entity` because that is what the lifecycle events deliver (`$event->entity` is typed `Entity`). The `instanceof IdentityInterface` check inside `purge()` then narrows correctly. Non-Entity classes implementing `IdentityInterface` are outside the bridge package's scope and would be handled by future bridge packages (e.g. a hypothetical component bridge).

## Requirements (Test Descriptions)

- [x] `it purges every identity returned by an entity implementing IdentityInterface`
- [x] `it does nothing when the entity does not implement IdentityInterface`
- [x] `it does nothing when getIdentities returns an empty array`
- [x] `it invokes IdentityPurger from PurgeOnEntityCreated when EntityCreated is dispatched`
- [x] `it invokes IdentityPurger from PurgeOnEntityUpdated when EntityUpdated is dispatched`
- [x] `it invokes IdentityPurger from PurgeOnEntityDeleted when EntityDeleted is dispatched`
- [x] `it places each observer class under src/Observer/ so ObserverDiscovery picks them up`
- [x] `it declares PurgeOnEntityCreated with #[Observer(event: EntityCreated::class)] via reflection`
- [x] `it declares PurgeOnEntityUpdated with #[Observer(event: EntityUpdated::class)] via reflection`
- [x] `it declares PurgeOnEntityDeleted with #[Observer(event: EntityDeleted::class)] via reflection`
- [x] `it does not throw when purgeTag returns false (best-effort purge)`

## Acceptance Criteria

- All requirements have passing tests
- Each observer class is `readonly class`, single `handle(EventClass $event): void` method
- `IdentityPurger` is `readonly class`, single public method `purge(Entity $entity): void`
- All three observers declare `#[Observer(event: EntityX::class)]` correctly
- Tests use fakes/stubs for `PageCacheInterface`, not the file driver
- No filesystem I/O in this task's tests

## Implementation Notes

- Created `packages/page-cache-entity/src/IdentityPurger.php` — `readonly class` with single `purge(Entity $entity): void` method
- Created `packages/page-cache-entity/src/Observer/PurgeOnEntityCreated.php` — `readonly class` with `#[Observer(event: EntityCreated::class)]`
- Created `packages/page-cache-entity/src/Observer/PurgeOnEntityUpdated.php` — `readonly class` with `#[Observer(event: EntityUpdated::class)]`
- Created `packages/page-cache-entity/src/Observer/PurgeOnEntityDeleted.php` — `readonly class` with `#[Observer(event: EntityDeleted::class)]`
- Created test files using fake `PageCacheInterface` implementations (no filesystem I/O)
- Also fixed root `composer.json` to add `packages/page-cache-entity` path repository, `marko/page-cache-entity: self.version` requirement, and `Marko\PageCache\Entity\Tests\` autoload-dev entry (these were missing from task 020)
- Added `page-cache-entity` to GitHub issue templates (bug_report.yml and feature_request.yml)
- Tests run via Docker (`marko-playground-app` container, PHP 8.5.6)
