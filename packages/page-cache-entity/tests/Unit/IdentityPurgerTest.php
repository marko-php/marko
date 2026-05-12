<?php

declare(strict_types=1);

use Marko\Database\Entity\Entity;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Contracts\IdentityInterface;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\Entity\IdentityPurger;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

function makeFakePageCache(): PageCacheInterface
{
    return new class () implements PageCacheInterface
    {
        /** @var array<string> */
        public array $purgedTags = [];

        public bool $purgeTagReturn = true;

        public function lookup(Request $request): ?Response
        {
            return null;
        }

        public function store(
            Request $request,
            Response $response,
            CachePolicy $policy,
        ): Response {
            return $response;
        }

        public function purgeUrl(string $url): bool
        {
            return true;
        }

        public function purgeTag(string $tag): bool
        {
            $this->purgedTags[] = $tag;

            return $this->purgeTagReturn;
        }

        public function clear(): bool
        {
            return true;
        }
    };
}

it('purges every identity returned by an entity implementing IdentityInterface', function (): void {
    $pageCache = makeFakePageCache();
    $purger = new IdentityPurger($pageCache);

    $entity = new class () extends Entity implements IdentityInterface
    {
        public function getIdentities(): array
        {
            return ['tag_a', 'tag_b', 'tag_c'];
        }
    };

    $purger->purge($entity);

    expect($pageCache->purgedTags)->toBe(['tag_a', 'tag_b', 'tag_c']);
});

it('does nothing when the entity does not implement IdentityInterface', function (): void {
    $pageCache = makeFakePageCache();
    $purger = new IdentityPurger($pageCache);

    $entity = new class () extends Entity {};

    $purger->purge($entity);

    expect($pageCache->purgedTags)->toBeEmpty();
});

it('does nothing when getIdentities returns an empty array', function (): void {
    $pageCache = makeFakePageCache();
    $purger = new IdentityPurger($pageCache);

    $entity = new class () extends Entity implements IdentityInterface
    {
        public function getIdentities(): array
        {
            return [];
        }
    };

    $purger->purge($entity);

    expect($pageCache->purgedTags)->toBeEmpty();
});

it('does not throw when purgeTag returns false (best-effort purge)', function (): void {
    $pageCache = makeFakePageCache();
    $pageCache->purgeTagReturn = false;
    $purger = new IdentityPurger($pageCache);

    $entity = new class () extends Entity implements IdentityInterface
    {
        public function getIdentities(): array
        {
            return ['tag_x'];
        }
    };

    $purger->purge($entity);

    expect($pageCache->purgedTags)->toBe(['tag_x']);
});
