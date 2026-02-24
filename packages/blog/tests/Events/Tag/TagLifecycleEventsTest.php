<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Events\Tag;

use DateTimeImmutable;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Entity\TagInterface;
use Marko\Blog\Events\Tag\TagCreated;
use Marko\Blog\Events\Tag\TagDeleted;
use Marko\Blog\Events\Tag\TagUpdated;
use Marko\Blog\Repositories\TagRepository;
use Marko\Blog\Services\SlugGenerator;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Testing\Fake\FakeEventDispatcher;
use RuntimeException;

it('dispatches TagCreated event when tag is created', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createTagEventMockConnection(isNew: true);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        $dispatcher,
    );

    $tag = new Tag();
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $repository->save($tag);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(TagCreated::class);
});

it('dispatches TagUpdated event when tag is modified', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createTagEventMockConnection(isNew: false);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        $dispatcher,
    );

    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP Updated';
    $tag->slug = 'php-updated';

    $repository->save($tag);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(TagUpdated::class);
});

it('dispatches TagDeleted event when tag is removed', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createTagEventMockConnection(isNew: false, hasAssociatedPosts: false);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        $dispatcher,
    );

    $tag = new Tag();
    $tag->id = 1;
    $tag->name = 'PHP';
    $tag->slug = 'php';

    $repository->delete($tag);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(TagDeleted::class);
});

it('includes full tag entity in event data', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createTagEventMockConnection(isNew: true);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        $dispatcher,
    );

    $tag = new Tag();
    $tag->name = 'JavaScript';
    $tag->slug = 'javascript';

    $repository->save($tag);

    /** @var TagCreated $event */
    $event = $dispatcher->dispatched[0];

    expect($event->getTag())->toBeInstanceOf(TagInterface::class)
        ->and($event->getTag()->getName())->toBe('JavaScript')
        ->and($event->getTag()->getSlug())->toBe('javascript');
});

it('includes timestamp in all events', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createTagEventMockConnection(isNew: true);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = new SlugGenerator();

    $repository = new TagRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        $dispatcher,
    );

    $beforeSave = new DateTimeImmutable();

    $tag = new Tag();
    $tag->name = 'TypeScript';
    $tag->slug = 'typescript';

    $repository->save($tag);

    $afterSave = new DateTimeImmutable();

    /** @var TagCreated $event */
    $event = $dispatcher->dispatched[0];

    expect($event->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($event->getTimestamp()->getTimestamp())->toBeGreaterThanOrEqual($beforeSave->getTimestamp())
        ->and($event->getTimestamp()->getTimestamp())->toBeLessThanOrEqual($afterSave->getTimestamp());
});

// Helper function to create mock connection for event tests
function createTagEventMockConnection(
    bool $isNew = true,
    bool $hasAssociatedPosts = false,
): ConnectionInterface {
    return new class ($isNew, $hasAssociatedPosts) implements ConnectionInterface
    {
        public function __construct(
            private bool $isNew,
            private bool $hasAssociatedPosts,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            // For slug uniqueness check
            if (str_contains($sql, 'slug')) {
                return [];
            }

            // For associated posts count
            if (str_contains($sql, 'COUNT') && str_contains($sql, 'post_tags')) {
                return [['count' => $this->hasAssociatedPosts ? 1 : 0]];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->isNew ? 1 : 0;
        }
    };
}
