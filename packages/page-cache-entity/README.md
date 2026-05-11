# marko/page-cache-entity

Auto-purge page cache tags when entities change.

## Overview

`marko/page-cache-entity` observes `EntityCreated`, `EntityUpdated`, and `EntityDeleted` events from `marko/database`. For each entity that implements `IdentityInterface` (from `marko/page-cache`), it invokes `PageCacheInterface::purgeTag()` for every tag returned by `getIdentities()`. No wiring required — the three observer classes self-register via `#[Observer]` discovery.

## Installation

```bash
composer require marko/page-cache-entity
```

## Usage

Implement `IdentityInterface` on your entity and return the cache tags it carries:

```php
use Marko\Database\Entity\Entity;
use Marko\PageCache\Contracts\IdentityInterface;

class Product extends Entity implements IdentityInterface
{
    public function getIdentities(): array
    {
        return ['product-' . $this->id];
    }
}
```

Tag your route with the same tag so the page cache can track the relationship:

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

When the product is saved, the cached page is purged automatically:

```php
$repository->save($product); // purges 'product-42' — no extra code needed
```

## How It Works

- Three observer classes (`PurgeOnEntityCreated`, `PurgeOnEntityUpdated`, `PurgeOnEntityDeleted`) are auto-discovered via `#[Observer]` and listen to the corresponding database events.
- Each observer delegates to `IdentityPurger::purge($entity)`.
- `IdentityPurger` no-ops silently on entities that do not implement `IdentityInterface`.
- Return values from `purgeTag()` are intentionally ignored: drivers commonly return `true` even when no cached page used the tag yet (nothing to purge is not an error), and the rare `false` cases (I/O hiccups) are kept silent because surfacing them mid-save would be disruptive.

## Customization

No customization is needed for standard use cases. If you need different behavior (for example, async purging via a queue), you can write your own observer and disable the bundled ones via a `#[Preference]`. In practice, the defaults cover the common case.

## API Reference

```php
IdentityPurger::__construct(PageCacheInterface $pageCache)
IdentityPurger::purge(Entity $entity): void
```

## Documentation

Full usage, API reference, and examples: [marko/page-cache-entity](https://marko.build/docs/packages/page-cache-entity/)
