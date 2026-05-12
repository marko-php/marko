# Task 019: `IdentityInterface` in `marko/page-cache`

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description

Add a single-method `IdentityInterface` to `marko/page-cache` so that any object (most commonly an entity) can declare the cache tags it owns. Modelled on Magento's `IdentityInterface` — the same well-known name and the same single-method shape. The interface itself does nothing; it's a marker contract consumed by `marko/page-cache-entity` (task 021).

## Context

- Related files:
  - `packages/page-cache/src/Contracts/IdentityInterface.php` — new file
  - `packages/page-cache/src/Contracts/PageCacheInterface.php` — sibling for style reference
- Pattern to follow: All page-cache contracts live under `Marko\PageCache\Contracts\`. Single method, PSR-style PHPDoc for return type.

**Interface shape:**
```php
namespace Marko\PageCache\Contracts;

interface IdentityInterface
{
    /**
     * Cache tags this object owns. Returned tags will be purged from the
     * page cache when an observer determines this object has changed.
     *
     * @return array<string>
     */
    public function getIdentities(): array;
}
```

**Why this namespace and not `marko/database`:** Page cache owns its own contract. Entities depend on `marko/page-cache` to implement it; database stays unaware of caching. Applications already using both packages have both available.

**Why `getIdentities()` and not `cacheTags()`:** Mirrors Magento exactly. Familiar to anyone coming from Magento, and "identity" carries the right semantic — a stable tag that names *this object*, irrespective of whether the cache is currently storing anything for it.

## Requirements (Test Descriptions)

- [x] `it defines IdentityInterface in the Marko PageCache Contracts namespace`
- [x] `it declares a getIdentities method that returns an array of strings`
- [x] `it allows arbitrary classes to implement IdentityInterface`

## Acceptance Criteria

- All requirements have passing tests
- File follows project standards (strict types, namespace under `Marko\PageCache\Contracts`)
- No implementation classes added in this task — interface only

## Implementation Notes

- Created `packages/page-cache/src/Contracts/IdentityInterface.php` with single `getIdentities(): array` method
- Created `packages/page-cache/tests/Unit/Contracts/IdentityInterfaceTest.php` with 3 tests covering namespace, method signature, and implementability
- Tests run via Docker (`marko-playground-app` container, PHP 8.5.6) since local environment only has PHP 8.3
