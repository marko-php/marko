<?php

declare(strict_types=1);

use Marko\Database\Entity\Entity;
use Marko\Database\Events\EntityDeleted;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Contracts\IdentityInterface;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\Entity\IdentityPurger;
use Marko\PageCache\Entity\Observer\PurgeOnEntityDeleted;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

it('invokes IdentityPurger from PurgeOnEntityDeleted when EntityDeleted is dispatched', function (): void {
    $fakePageCache = new class () implements PageCacheInterface
    {
        /** @var array<string> */
        public array $purgedTags = [];

        public function lookup(Request $request): ?Response
        {
            return null;
        }

        public function store(Request $request, Response $response, CachePolicy $policy): Response
        {
            return $response;
        }

        public function purgeUrl(string $url): bool
        {
            return true;
        }

        public function purgeTag(string $tag): bool
        {
            $this->purgedTags[] = $tag;

            return true;
        }

        public function clear(): bool
        {
            return true;
        }
    };

    $purger = new IdentityPurger($fakePageCache);
    $observer = new PurgeOnEntityDeleted($purger);

    $entity = new class () extends Entity implements IdentityInterface
    {
        public function getIdentities(): array
        {
            return ['tag_deleted'];
        }
    };

    $event = new EntityDeleted($entity, $entity::class);
    $observer->handle($event);

    expect($fakePageCache->purgedTags)->toBe(['tag_deleted']);
});
