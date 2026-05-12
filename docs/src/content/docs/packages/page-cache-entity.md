---
title: marko/page-cache-entity
description: Bridge package that auto-purges page-cache tags when entities implementing IdentityInterface are saved or deleted.
---

Bridge package that auto-purges page-cache tags when entities implementing `IdentityInterface` are saved or deleted. This package observes `EntityCreated`, `EntityUpdated`, and `EntityDeleted` events from `marko/database`. For each entity that implements `IdentityInterface` (from `marko/page-cache`), it calls `PageCacheInterface::purgeTag()` for every tag returned by `getIdentities()`. No manual wiring is required --- the three observer classes self-register via `#[Observer]` discovery.

## Installation

```bash
composer require marko/page-cache-entity
```

This package requires `marko/page-cache` and `marko/database`.

## Usage

### Implementing IdentityInterface

Implement `IdentityInterface` on your entity and return the cache tags it carries:

```php
use Marko\Database\Entity\Entity;
use Marko\PageCache\Contracts\IdentityInterface;

class Product extends Entity implements IdentityInterface
{
    public function getIdentities(): array
    {
        return ['products', "product-{$this->id}"];
    }
}
```

### Tagging Controller Actions

Tag the corresponding route with the same cache tags so the page cache tracks the relationship:

```php
use Marko\PageCache\Attributes\Cacheable;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class ProductController
{
    #[Get('/products/{id}')]
    #[Cacheable(ttl: 3600, tags: ['product-{id}'])]
    public function show(int $id): Response
    {
        return Response::ok($this->productRepository->find($id));
    }
}
```

### Automatic Purge on Save

When the entity is saved or deleted, the associated cached page is purged automatically:

```php
$repository->save($product); // purges 'product-42' — no extra code needed
```

## How It Works

Three observer classes (`PurgeOnEntityCreated`, `PurgeOnEntityUpdated`, `PurgeOnEntityDeleted`) are auto-discovered via `#[Observer]` and listen to the corresponding database events. Each observer delegates to `IdentityPurger::purge($entity)`.

`IdentityPurger` silently no-ops on entities that do not implement `IdentityInterface`. Return values from `purgeTag()` are intentionally ignored: drivers commonly return `true` even when no cached page carried that tag, and surfacing `false` (I/O hiccups) mid-save would be disruptive.

## Customization

No customization is needed for standard use cases. If you need different behaviour --- for example, async purging via a queue --- write your own observer and disable the bundled ones via a `#[Preference]`. In practice the defaults cover the common case.

## API Reference

### IdentityPurger

```php
use Marko\PageCache\Entity\IdentityPurger;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\Database\Entity\Entity;

public function __construct(PageCacheInterface $pageCache);
public function purge(Entity $entity): void;
```

`purge()` checks whether `$entity` implements `IdentityInterface`. If it does, it calls `purgeTag()` on the page-cache driver for each tag returned by `getIdentities()`. If it does not, the call is a no-op.

## Related Packages

- [marko/page-cache](/docs/packages/page-cache/) --- Contracts, middleware, and CLI. Defines `IdentityInterface` and `PageCacheInterface`
- [marko/page-cache-file](/docs/packages/page-cache-file/) --- File-based driver implementation
- [marko/database](/docs/packages/database/) --- Provides the `EntityCreated`, `EntityUpdated`, and `EntityDeleted` events
