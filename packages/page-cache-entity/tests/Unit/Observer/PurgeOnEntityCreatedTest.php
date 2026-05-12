<?php

declare(strict_types=1);

use Marko\Database\Entity\Entity;
use Marko\Database\Events\EntityCreated;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Contracts\IdentityInterface;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\Entity\IdentityPurger;
use Marko\PageCache\Entity\Observer\PurgeOnEntityCreated;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

it('invokes IdentityPurger from PurgeOnEntityCreated when EntityCreated is dispatched', function (): void {
    $fakePageCache = new class () implements PageCacheInterface
    {
        /** @var array<string> */
        public array $purgedTags = [];

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

            return true;
        }

        public function clear(): bool
        {
            return true;
        }
    };

    $purger = new IdentityPurger($fakePageCache);
    $observer = new PurgeOnEntityCreated($purger);

    $entity = new class () extends Entity implements IdentityInterface
    {
        public function getIdentities(): array
        {
            return ['tag_created'];
        }
    };

    $event = new EntityCreated($entity, $entity::class);
    $observer->handle($event);

    expect($fakePageCache->purgedTags)->toBe(['tag_created']);
});
