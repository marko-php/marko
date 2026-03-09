<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Events\Category;

use Closure;
use DateTimeImmutable;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\CategoryInterface;
use Marko\Blog\Events\Category\CategoryCreated;
use Marko\Blog\Events\Category\CategoryDeleted;
use Marko\Blog\Events\Category\CategoryUpdated;
use Marko\Blog\Repositories\CategoryRepository;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Core\Event\Event;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Testing\Fake\FakeEventDispatcher;
use ReflectionClass;
use RuntimeException;

it('dispatches CategoryCreated event when category is created', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createMockEventConnection([], lastInsertId: 1);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockEventSlugGenerator();

    $repository = new CategoryRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        eventDispatcher: $dispatcher,
    );

    $category = new Category();
    $category->name = 'Technology';
    $category->slug = 'technology';

    $repository->save($category);

    $categoryCreatedEvents = array_values(array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryCreated,
    ));

    expect($categoryCreatedEvents)->toHaveCount(1)
        ->and($categoryCreatedEvents[0])->toBeInstanceOf(CategoryCreated::class);
});

it('dispatches CategoryUpdated event when category is modified', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createMockEventConnection([]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockEventSlugGenerator();

    $repository = new CategoryRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        eventDispatcher: $dispatcher,
    );

    $category = new Category();
    $category->id = 1; // Existing category (has ID)
    $category->name = 'Technology Updated';
    $category->slug = 'technology';

    $repository->save($category);

    $categoryUpdatedEvents = array_values(array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryUpdated,
    ));

    expect($categoryUpdatedEvents)->toHaveCount(1)
        ->and($categoryUpdatedEvents[0])->toBeInstanceOf(CategoryUpdated::class);
});

it('dispatches CategoryDeleted event when category is removed', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createMockEventConnection(deletionAllowed: true);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockEventSlugGenerator();

    $repository = new CategoryRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        eventDispatcher: $dispatcher,
    );

    $category = new Category();
    $category->id = 1;
    $category->name = 'Technology';
    $category->slug = 'technology';

    $repository->delete($category);

    $categoryDeletedEvents = array_values(array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryDeleted,
    ));

    expect($categoryDeletedEvents)->toHaveCount(1)
        ->and($categoryDeletedEvents[0])->toBeInstanceOf(CategoryDeleted::class);
});

it('includes full category entity in event data', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createMockEventConnection([], lastInsertId: 1);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockEventSlugGenerator();

    $repository = new CategoryRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        eventDispatcher: $dispatcher,
    );

    $category = new Category();
    $category->name = 'Technology';
    $category->slug = 'technology';

    $repository->save($category);

    $events = array_values(array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryCreated,
    ));

    /** @var CategoryCreated $event */
    $event = $events[0];

    expect($event->getCategory())->toBeInstanceOf(CategoryInterface::class)
        ->and($event->getCategory()->getName())->toBe('Technology')
        ->and($event->getCategory()->getSlug())->toBe('technology');
});

it('includes parent category in event data if exists', function (): void {
    $dispatcher = new FakeEventDispatcher();

    // Connection that returns parent category when queried
    $parentData = [
        [
            'id' => 1,
            'name' => 'Programming',
            'slug' => 'programming',
            'parent_id' => null,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ];
    $connection = createMockEventConnection($parentData, lastInsertId: 2);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockEventSlugGenerator();

    $repository = new CategoryRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        eventDispatcher: $dispatcher,
    );

    $category = new Category();
    $category->name = 'PHP';
    $category->slug = 'php';
    $category->parentId = 1;

    $repository->save($category);

    $events = array_values(array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryCreated,
    ));

    /** @var CategoryCreated $event */
    $event = $events[0];

    expect($event->getParent())->toBeInstanceOf(CategoryInterface::class)
        ->and($event->getParent()->getName())->toBe('Programming');
});

it('includes timestamp in all events', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createMockEventConnection([], lastInsertId: 1);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = createMockEventSlugGenerator();

    $repository = new CategoryRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        eventDispatcher: $dispatcher,
    );

    $category = new Category();
    $category->name = 'Technology';
    $category->slug = 'technology';

    $beforeSave = new DateTimeImmutable();
    $repository->save($category);
    $afterSave = new DateTimeImmutable();

    $events = array_values(array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryCreated,
    ));

    /** @var CategoryCreated $event */
    $event = $events[0];

    expect($event->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($event->getTimestamp()->getTimestamp())->toBeGreaterThanOrEqual($beforeSave->getTimestamp())
        ->and($event->getTimestamp()->getTimestamp())->toBeLessThanOrEqual($afterSave->getTimestamp());
});

it('all category events are immutable', function (): void {
    $createdEvent = new ReflectionClass(CategoryCreated::class);
    expect($createdEvent->getProperty('category')->isReadOnly())->toBeTrue()
        ->and($createdEvent->getProperty('parent')->isReadOnly())->toBeTrue()
        ->and($createdEvent->getProperty('timestamp')->isReadOnly())->toBeTrue();

    $updatedEvent = new ReflectionClass(CategoryUpdated::class);
    expect($updatedEvent->getProperty('category')->isReadOnly())->toBeTrue()
        ->and($updatedEvent->getProperty('parent')->isReadOnly())->toBeTrue()
        ->and($updatedEvent->getProperty('timestamp')->isReadOnly())->toBeTrue();

    $deletedEvent = new ReflectionClass(CategoryDeleted::class);
    expect($deletedEvent->getProperty('category')->isReadOnly())->toBeTrue()
        ->and($deletedEvent->getProperty('parent')->isReadOnly())->toBeTrue()
        ->and($deletedEvent->getProperty('timestamp')->isReadOnly())->toBeTrue();
});

it('category events extend base Event class', function (): void {
    expect(is_subclass_of(CategoryCreated::class, Event::class))->toBeTrue()
        ->and(is_subclass_of(CategoryUpdated::class, Event::class))->toBeTrue()
        ->and(is_subclass_of(CategoryDeleted::class, Event::class))->toBeTrue();
});

// Helper functions

function createMockEventConnection(
    array $queryResult = [],
    int $lastInsertId = 1,
    bool $deletionAllowed = false,
): ConnectionInterface {
    return new class ($queryResult, $lastInsertId, $deletionAllowed) implements ConnectionInterface
    {
        public function __construct(
            private array $queryResult,
            private int $lastInsertId,
            private bool $deletionAllowed,
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
            // Handle deletion checks
            if ($this->deletionAllowed) {
                if (str_contains($sql, 'post_categories') || str_contains($sql, 'parent_id')) {
                    return [['count' => 0]];
                }
            }

            return $this->queryResult;
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
            return $this->lastInsertId;
        }
    };
}

function createMockEventSlugGenerator(): SlugGeneratorInterface
{
    return new class () implements SlugGeneratorInterface
    {
        public function generate(
            string $title,
            ?Closure $uniquenessChecker = null,
        ): string {
            return strtolower(str_replace(' ', '-', $title));
        }
    };
}
