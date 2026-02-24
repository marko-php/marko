<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Events\Post;

use DateTimeImmutable;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Post\PostCreated;
use Marko\Blog\Events\Post\PostDeleted;
use Marko\Blog\Events\Post\PostPublished;
use Marko\Blog\Events\Post\PostScheduled;
use Marko\Blog\Events\Post\PostUpdated;
use Marko\Blog\Repositories\PostRepository;
use Marko\Core\Event\Event;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Testing\Fake\FakeEventDispatcher;
use RuntimeException;

it('dispatches PostCreated event when post is saved first time', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createPostEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $dispatcher,
    );

    $post = new Post(
        title: 'Test Post',
        content: 'Test content',
        authorId: 1,
        slug: 'test-post',
    );

    $repository->save($post);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(PostCreated::class);
});

it('dispatches PostUpdated event when existing post is modified', function (): void {
    $dispatcher = new FakeEventDispatcher();

    // Return post data when queried (simulating an existing record)
    $postData = [
        'id' => 1,
        'title' => 'Original Post',
        'slug' => 'original-post',
        'content' => 'Original content',
        'summary' => null,
        'status' => 'draft',
        'author_id' => 1,
        'scheduled_at' => null,
        'published_at' => null,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $connection = createPostEventTestMockConnection(
        queryCallback: fn () => [$postData],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $dispatcher,
    );

    // Fetch the post from "database" (this tracks original state)
    $post = $repository->find(1);

    // Modify the post
    $post->title = 'Updated Post';

    $repository->save($post);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(PostUpdated::class);
});

it('dispatches PostPublished event when status changes to published', function (): void {
    $dispatcher = new FakeEventDispatcher();

    // Return draft post data when queried
    $postData = [
        'id' => 1,
        'title' => 'Draft Post',
        'slug' => 'draft-post',
        'content' => 'Content here',
        'summary' => null,
        'status' => 'draft',
        'author_id' => 1,
        'scheduled_at' => null,
        'published_at' => null,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $connection = createPostEventTestMockConnection(
        queryCallback: fn () => [$postData],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $dispatcher,
    );

    // Fetch the draft post
    $post = $repository->find(1);

    // Change status to published
    $post->setStatus(PostStatus::Published);

    $repository->save($post);

    // Should dispatch both PostUpdated and PostPublished
    $publishedEvents = array_filter(
        $dispatcher->dispatched,
        fn (Event $event) => $event instanceof PostPublished,
    );

    expect($publishedEvents)->toHaveCount(1);
});

it('dispatches PostScheduled event when status changes to scheduled', function (): void {
    $dispatcher = new FakeEventDispatcher();

    // Return draft post data when queried
    $postData = [
        'id' => 1,
        'title' => 'Draft Post',
        'slug' => 'draft-post',
        'content' => 'Content here',
        'summary' => null,
        'status' => 'draft',
        'author_id' => 1,
        'scheduled_at' => null,
        'published_at' => null,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $connection = createPostEventTestMockConnection(
        queryCallback: fn () => [$postData],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $dispatcher,
    );

    // Fetch the draft post
    $post = $repository->find(1);

    // Set scheduled_at and change status to scheduled
    $post->setScheduledAt(new DateTimeImmutable('+1 week'));
    $post->setStatus(PostStatus::Scheduled);

    $repository->save($post);

    // Should dispatch both PostUpdated and PostScheduled
    $scheduledEvents = array_filter(
        $dispatcher->dispatched,
        fn (Event $event) => $event instanceof PostScheduled,
    );

    expect($scheduledEvents)->toHaveCount(1);
});

it('dispatches PostDeleted event when post is removed', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createPostEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $dispatcher,
    );

    $post = new Post(
        title: 'Post to Delete',
        content: 'Content here',
        authorId: 1,
        slug: 'post-to-delete',
    );
    $post->id = 1;

    $repository->delete($post);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(PostDeleted::class);
});

it('includes full post entity in event data', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $connection = createPostEventTestMockConnection(
        queryCallback: fn () => [],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $dispatcher,
    );

    $post = new Post(
        title: 'My Complete Post',
        content: 'This is the full content of the post',
        authorId: 42,
        slug: 'my-complete-post',
        summary: 'A brief summary',
    );

    $repository->save($post);

    expect($dispatcher->dispatched)->toHaveCount(1);

    /** @var PostCreated $event */
    $event = $dispatcher->dispatched[0];
    $eventPost = $event->getPost();

    expect($eventPost)->toBeInstanceOf(PostInterface::class)
        ->and($eventPost->getTitle())->toBe('My Complete Post')
        ->and($eventPost->getContent())->toBe('This is the full content of the post')
        ->and($eventPost->getSlug())->toBe('my-complete-post')
        ->and($eventPost->getAuthorId())->toBe(42)
        ->and($eventPost->getSummary())->toBe('A brief summary');
});

it('includes previous status in status change events', function (): void {
    $dispatcher = new FakeEventDispatcher();

    // Return scheduled post data when queried
    $postData = [
        'id' => 1,
        'title' => 'Scheduled Post',
        'slug' => 'scheduled-post',
        'content' => 'Content here',
        'summary' => null,
        'status' => 'scheduled',
        'author_id' => 1,
        'scheduled_at' => '2024-01-20 10:00:00',
        'published_at' => null,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $connection = createPostEventTestMockConnection(
        queryCallback: fn () => [$postData],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $dispatcher,
    );

    // Fetch the scheduled post
    $post = $repository->find(1);

    // Change status from scheduled to published
    $post->setStatus(PostStatus::Published);

    $repository->save($post);

    // Find the PostPublished event
    $publishedEvents = array_filter(
        $dispatcher->dispatched,
        fn (Event $event) => $event instanceof PostPublished,
    );

    expect($publishedEvents)->toHaveCount(1);

    /** @var PostPublished $publishedEvent */
    $publishedEvent = array_values($publishedEvents)[0];

    expect($publishedEvent->getPreviousStatus())->toBe(PostStatus::Scheduled);
});

it('includes timestamp in all events', function (): void {
    $dispatcher = new FakeEventDispatcher();

    // Return draft post data when queried
    $postData = [
        'id' => 1,
        'title' => 'Test Post',
        'slug' => 'test-post',
        'content' => 'Content',
        'summary' => null,
        'status' => 'draft',
        'author_id' => 1,
        'scheduled_at' => null,
        'published_at' => null,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ];

    $connection = createPostEventTestMockConnection(
        queryCallback: fn () => [$postData],
        executeCallback: fn () => 1,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        eventDispatcher: $dispatcher,
    );

    $beforeCreate = new DateTimeImmutable();

    // Create a new post
    $newPost = new Post(
        title: 'New Post',
        content: 'Content',
        authorId: 1,
        slug: 'new-post',
    );
    $repository->save($newPost);

    // Fetch and update an existing post
    $existingPost = $repository->find(1);
    $existingPost->title = 'Updated';
    $repository->save($existingPost);

    // Change status to published
    $existingPost->setStatus(PostStatus::Published);
    $repository->save($existingPost);

    // Delete post
    $repository->delete($newPost);

    $afterAll = new DateTimeImmutable();

    // All events should have timestamps within the test window
    foreach ($dispatcher->dispatched as $event) {
        expect($event->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($event->getTimestamp() >= $beforeCreate)->toBeTrue()
            ->and($event->getTimestamp() <= $afterAll)->toBeTrue();
    }
});

// Helper functions for post event tests

function createPostEventTestMockConnection(
    callable $queryCallback,
    callable $executeCallback,
): ConnectionInterface {
    return new readonly class ($queryCallback, $executeCallback) implements ConnectionInterface
    {
        public function __construct(
            private mixed $queryCallback,
            private mixed $executeCallback,
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
            return ($this->queryCallback)($sql, $bindings);
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return ($this->executeCallback)($sql, $bindings);
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };
}
