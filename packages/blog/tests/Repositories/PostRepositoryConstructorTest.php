<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Post\PostCreated;
use Marko\Blog\Events\Post\PostDeleted;
use Marko\Blog\Events\Post\PostPublished;
use Marko\Blog\Events\Post\PostScheduled;
use Marko\Blog\Events\Post\PostUpdated;
use Marko\Blog\Repositories\PostRepository;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityDeleting;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use Marko\Testing\Fake\FakeEventDispatcher;
use ReflectionClass;
use RuntimeException;

function makeConnection(array $queryResult = [], ?array &$history = null): ConnectionInterface
{
    $history ??= [];

    return new class ($queryResult, $history) implements ConnectionInterface
    {
        public function __construct(
            private array $queryResult,
            private array &$history,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            $this->history[] = ['sql' => $sql, 'bindings' => $bindings];

            return $this->queryResult;
        }

        public function execute(string $sql, array $bindings = []): int
        {
            $this->history[] = ['sql' => $sql, 'bindings' => $bindings];

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 42;
        }
    };
}

/**
 * Create a repository and hydrator pair that share the same EntityHydrator instance.
 * This is required so getOriginalValues() works correctly for dirty-checking.
 *
 * @return array{PostRepository, EntityHydrator}
 */
function makeRepositoryWithHydrator(?FakeEventDispatcher $dispatcher = null): array
{
    $connection = makeConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $repository = new PostRepository($connection, $metadataFactory, $hydrator, null, $dispatcher);

    return [$repository, $hydrator];
}

function makeNewPost(): Post
{
    // id defaults to null => treated as new by hydrator isNew() check
    return new Post('Test Title', 'Test Content', 1, null, 'test-slug');
}

function makeExistingPost(EntityHydrator $hydrator, PostStatus $status = PostStatus::Draft): Post
{
    $metadataFactory = new EntityMetadataFactory();
    $metadata = $metadataFactory->parse(Post::class);

    $scheduledAt = $status === PostStatus::Scheduled ? '2099-01-01 00:00:00' : null;

    // Use the same hydrator as the repository so originalValues are tracked correctly
    return $hydrator->hydrate(Post::class, [
        'id' => 1,
        'title' => 'Existing Post',
        'content' => 'Content',
        'author_id' => 1,
        'slug' => 'existing-post',
        'status' => $status->value,
        'summary' => null,
        'scheduled_at' => $scheduledAt,
        'published_at' => null,
        'created_at' => '2024-01-01 00:00:00',
        'updated_at' => '2024-01-01 00:00:00',
    ], $metadata);
}

it('constructs without explicit EventDispatcherInterface parameter', function (): void {
    $reflection = new ReflectionClass(PostRepository::class);
    $constructor = $reflection->getMethod('__construct');

    // Constructor must be inherited from the base Repository, not defined in PostRepository
    expect($constructor->getDeclaringClass()->getName())->not->toBe(PostRepository::class);
});

it('dispatches lifecycle events and PostCreated domain event on new post save', function (): void {
    $dispatcher = new FakeEventDispatcher();
    [$repository] = makeRepositoryWithHydrator($dispatcher);
    $post = makeNewPost();

    $repository->save($post);

    $dispatcher->assertDispatched(EntityCreating::class);
    $dispatcher->assertDispatched(EntityCreated::class);
    $dispatcher->assertDispatched(PostCreated::class);

    $postCreatedEvents = $dispatcher->dispatched(PostCreated::class);
    expect($postCreatedEvents[0]->getPost())->toBe($post);
});

it('dispatches lifecycle events and PostUpdated domain event on existing post save', function (): void {
    $dispatcher = new FakeEventDispatcher();
    [$repository, $hydrator] = makeRepositoryWithHydrator($dispatcher);
    $post = makeExistingPost($hydrator);
    $post->title = 'Updated Title';

    $repository->save($post);

    $dispatcher->assertDispatched(EntityUpdating::class);
    $dispatcher->assertDispatched(EntityUpdated::class);
    $dispatcher->assertDispatched(PostUpdated::class);

    $postUpdatedEvents = $dispatcher->dispatched(PostUpdated::class);
    expect($postUpdatedEvents[0]->getPost())->toBe($post);
});

it('dispatches PostPublished event on status change to published', function (): void {
    $dispatcher = new FakeEventDispatcher();
    [$repository, $hydrator] = makeRepositoryWithHydrator($dispatcher);
    $post = makeExistingPost($hydrator, PostStatus::Draft);
    $post->setStatus(PostStatus::Published);

    $repository->save($post);

    $dispatcher->assertDispatched(PostPublished::class);
    $dispatcher->assertNotDispatched(PostScheduled::class);

    expect($dispatcher->dispatched(PostPublished::class))->toHaveCount(1)
        ->and($dispatcher->dispatched(PostScheduled::class))->toBeEmpty();
});

it('dispatches PostScheduled event on status change to scheduled', function (): void {
    $dispatcher = new FakeEventDispatcher();
    [$repository, $hydrator] = makeRepositoryWithHydrator($dispatcher);
    $post = makeExistingPost($hydrator, PostStatus::Draft);
    $post->scheduledAt = '2099-01-01 00:00:00';
    $post->setStatus(PostStatus::Scheduled);

    $repository->save($post);

    $dispatcher->assertDispatched(PostScheduled::class);
    $dispatcher->assertNotDispatched(PostPublished::class);

    expect($dispatcher->dispatched(PostScheduled::class))->toHaveCount(1)
        ->and($dispatcher->dispatched(PostPublished::class))->toBeEmpty();
});

it('dispatches PostDeleted event on delete', function (): void {
    $dispatcher = new FakeEventDispatcher();
    [$repository, $hydrator] = makeRepositoryWithHydrator($dispatcher);
    $post = makeExistingPost($hydrator);

    $repository->delete($post);

    $dispatcher->assertDispatched(EntityDeleting::class);
    $dispatcher->assertDispatched(EntityDeleted::class);
    $dispatcher->assertDispatched(PostDeleted::class);

    $postDeletedEvents = $dispatcher->dispatched(PostDeleted::class);
    expect($postDeletedEvents[0]->getPost())->toBe($post);
});
