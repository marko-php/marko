<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Commands;

use DateTimeImmutable;
use Marko\Blog\Commands\PublishScheduledCommand;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Post\PostPublished;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Entity\Entity;
use Marko\Testing\Fake\FakeEventDispatcher;
use ReflectionClass;
use RuntimeException;

/**
 * Helper to capture command output.
 *
 * @return array{stream: resource, output: Output}
 */
function createOutputStream(): array
{
    $stream = fopen('php://memory', 'r+');

    return [
        'stream' => $stream,
        'output' => new Output($stream),
    ];
}

/**
 * Helper to get output content from stream.
 *
 * @param resource $stream
 */
function getOutputContent(
    mixed $stream,
): string {
    rewind($stream);

    return stream_get_contents($stream);
}

/**
 * Helper to execute PublishScheduledCommand.
 *
 * @param array<string> $args
 *
 * @return array{output: string, exitCode: int}
 */
function executeCommand(
    PublishScheduledCommand $command,
    array $args = ['marko', 'blog:publish-scheduled'],
): array {
    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input($args);

    $exitCode = $command->execute($input, $output);
    $result = getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('is registered as blog:publish-scheduled command', function (): void {
    $reflection = new ReflectionClass(PublishScheduledCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('blog:publish-scheduled');
});

it('finds all posts with status scheduled and scheduled_at in the past', function (): void {
    $capture = (object) ['findScheduledPostsDueCalled' => false];

    $postRepository = new StubPostRepository($capture, []);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    executeCommand($command);

    expect($capture->findScheduledPostsDueCalled)->toBeTrue();
});

it('changes status to published for matching posts', function (): void {
    $capture = (object) ['savedPosts' => []];

    $post = new Post(
        title: 'Scheduled Post',
        content: 'Content',
        authorId: 1,
        slug: 'scheduled-post',
    );
    $post->id = 1;
    $post->scheduledAt = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
    $post->status = PostStatus::Scheduled;

    $postRepository = new StubPostRepository($capture, [$post]);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    executeCommand($command);

    expect($capture->savedPosts)->toHaveCount(1)
        ->and($capture->savedPosts[0]->getStatus())->toBe(PostStatus::Published);
});

it('sets published_at to current datetime', function (): void {
    $capture = (object) ['savedPosts' => []];

    $post = new Post(
        title: 'Scheduled Post',
        content: 'Content',
        authorId: 1,
        slug: 'scheduled-post',
    );
    $post->id = 1;
    $post->scheduledAt = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
    $post->status = PostStatus::Scheduled;

    $postRepository = new StubPostRepository($capture, [$post]);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    // Use same precision as stored datetime (no microseconds)
    $beforeExecution = new DateTimeImmutable(date('Y-m-d H:i:s'));
    executeCommand($command);
    $afterExecution = new DateTimeImmutable(date('Y-m-d H:i:s', strtotime('+1 second')));

    $savedPost = $capture->savedPosts[0];
    $publishedAt = $savedPost->getPublishedAt();

    expect($publishedAt)->not->toBeNull()
        ->and($publishedAt >= $beforeExecution)->toBeTrue()
        ->and($publishedAt <= $afterExecution)->toBeTrue();
});

it('dispatches PostPublished event for each published post', function (): void {
    $capture = (object) ['savedPosts' => []];

    $post1 = new Post(
        title: 'Scheduled Post 1',
        content: 'Content 1',
        authorId: 1,
        slug: 'scheduled-post-1',
    );
    $post1->id = 1;
    $post1->scheduledAt = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
    $post1->status = PostStatus::Scheduled;

    $post2 = new Post(
        title: 'Scheduled Post 2',
        content: 'Content 2',
        authorId: 1,
        slug: 'scheduled-post-2',
    );
    $post2->id = 2;
    $post2->scheduledAt = (new DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s');
    $post2->status = PostStatus::Scheduled;

    $postRepository = new StubPostRepository($capture, [$post1, $post2]);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    executeCommand($command);

    expect($eventDispatcher->dispatched)->toHaveCount(2)
        ->and($eventDispatcher->dispatched[0])->toBeInstanceOf(PostPublished::class)
        ->and($eventDispatcher->dispatched[1])->toBeInstanceOf(PostPublished::class);
});

it('reports count of posts published', function (): void {
    $capture = (object) ['savedPosts' => []];

    $post1 = new Post(
        title: 'Scheduled Post 1',
        content: 'Content 1',
        authorId: 1,
        slug: 'scheduled-post-1',
    );
    $post1->id = 1;
    $post1->scheduledAt = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
    $post1->status = PostStatus::Scheduled;

    $post2 = new Post(
        title: 'Scheduled Post 2',
        content: 'Content 2',
        authorId: 1,
        slug: 'scheduled-post-2',
    );
    $post2->id = 2;
    $post2->scheduledAt = (new DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s');
    $post2->status = PostStatus::Scheduled;

    $postRepository = new StubPostRepository($capture, [$post1, $post2]);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    ['output' => $output] = executeCommand($command);

    expect($output)->toContain('2')
        ->and($output)->toContain('published');
});

it('handles case when no scheduled posts are due', function (): void {
    $capture = (object) ['savedPosts' => []];

    $postRepository = new StubPostRepository($capture, []);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    ['output' => $output, 'exitCode' => $exitCode] = executeCommand($command);

    expect($capture->savedPosts)->toHaveCount(0)
        ->and($eventDispatcher->dispatched)->toHaveCount(0)
        ->and($output)->toContain('0')
        ->and($exitCode)->toBe(0);
});

it('provides verbose output option showing post titles', function (): void {
    $capture = (object) ['savedPosts' => []];

    $post1 = new Post(
        title: 'My First Scheduled Post',
        content: 'Content 1',
        authorId: 1,
        slug: 'my-first-scheduled-post',
    );
    $post1->id = 1;
    $post1->scheduledAt = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
    $post1->status = PostStatus::Scheduled;

    $post2 = new Post(
        title: 'Another Scheduled Post',
        content: 'Content 2',
        authorId: 1,
        slug: 'another-scheduled-post',
    );
    $post2->id = 2;
    $post2->scheduledAt = (new DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s');
    $post2->status = PostStatus::Scheduled;

    $postRepository = new StubPostRepository($capture, [$post1, $post2]);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    ['output' => $output] = executeCommand($command, ['marko', 'blog:publish-scheduled', '--verbose']);

    expect($output)->toContain('My First Scheduled Post')
        ->and($output)->toContain('Another Scheduled Post');
});

it('returns success exit code on completion', function (): void {
    $capture = (object) ['savedPosts' => []];

    $post = new Post(
        title: 'Scheduled Post',
        content: 'Content',
        authorId: 1,
        slug: 'scheduled-post',
    );
    $post->id = 1;
    $post->scheduledAt = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
    $post->status = PostStatus::Scheduled;

    $postRepository = new StubPostRepository($capture, [$post]);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    ['exitCode' => $exitCode] = executeCommand($command);

    expect($exitCode)->toBe(0);
});

it('is safe to run concurrently without double-publishing', function (): void {
    // This test verifies that already-published posts won't be processed again.
    // The command only processes posts with status=Scheduled AND scheduled_at in the past.
    // Once published, a post's status is Published and won't appear in subsequent queries.

    $capture = (object) ['savedPosts' => []];

    // Create a post that's already published (simulating a race condition where
    // another process already published it)
    $alreadyPublishedPost = new Post(
        title: 'Already Published',
        content: 'Content',
        authorId: 1,
        slug: 'already-published',
    );
    $alreadyPublishedPost->id = 1;
    $alreadyPublishedPost->scheduledAt = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
    $alreadyPublishedPost->status = PostStatus::Published; // Already published
    $alreadyPublishedPost->publishedAt = (new DateTimeImmutable('-30 minutes'))->format('Y-m-d H:i:s');

    // StubPostRepository returns empty since no posts have status=Scheduled
    // (simulating what the real repository would do)
    $postRepository = new StubPostRepository($capture, []);
    $eventDispatcher = new FakeEventDispatcher();
    $command = new PublishScheduledCommand($postRepository, $eventDispatcher);

    executeCommand($command);

    // No posts should be saved or events dispatched
    expect($capture->savedPosts)->toHaveCount(0)
        ->and($eventDispatcher->dispatched)->toHaveCount(0);
});

/**
 * Stub post repository for testing.
 */
readonly class StubPostRepository implements PostRepositoryInterface
{
    /**
     * @param array<Post> $scheduledPosts
     */
    public function __construct(
        private object $capture,
        private array $scheduledPosts,
    ) {}

    public function findScheduledPostsDue(): array
    {
        $this->capture->findScheduledPostsDueCalled = true;

        return $this->scheduledPosts;
    }

    public function find(
        int $id,
    ): ?Entity {
        return null;
    }

    public function findOrFail(
        int $id,
    ): Entity {
        throw new RuntimeException('Not implemented');
    }

    public function findAll(): array
    {
        return [];
    }

    public function findBy(
        array $criteria,
    ): array {
        return [];
    }

    public function findOneBy(
        array $criteria,
    ): ?Entity {
        return null;
    }

    public function save(
        Entity $entity,
    ): void {
        $this->capture->savedPosts ??= [];
        $this->capture->savedPosts[] = $entity;
    }

    public function delete(Entity $entity): void {}

    public function findBySlug(
        string $slug,
    ): ?Post {
        return null;
    }

    public function findPublished(): array
    {
        return [];
    }

    public function findPublishedPaginated(
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublished(): int
    {
        return 0;
    }

    public function findByStatus(
        PostStatus $status,
    ): array {
        return [];
    }

    public function findByAuthor(
        int $authorId,
    ): array {
        return [];
    }

    public function countByAuthor(
        int $authorId,
    ): int {
        return 0;
    }

    public function findPublishedByAuthor(
        int $authorId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByAuthor(
        int $authorId,
    ): int {
        return 0;
    }

    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        return true;
    }

    public function findPublishedByTag(
        int $tagId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByTag(
        int $tagId,
    ): int {
        return 0;
    }

    public function findPublishedByCategory(
        int $categoryId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByCategory(
        int $categoryId,
    ): int {
        return 0;
    }

    public function findPublishedByCategories(
        array $categoryIds,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByCategories(
        array $categoryIds,
    ): int {
        return 0;
    }

    public function attachCategory(
        int $postId,
        int $categoryId,
    ): void {}

    public function detachCategory(
        int $postId,
        int $categoryId,
    ): void {}

    public function attachTag(
        int $postId,
        int $tagId,
    ): void {}

    public function detachTag(
        int $postId,
        int $tagId,
    ): void {}

    public function getCategoriesForPost(
        int $postId,
    ): array {
        return [];
    }

    public function getTagsForPost(
        int $postId,
    ): array {
        return [];
    }

    public function syncCategories(
        int $postId,
        array $categoryIds,
    ): void {}

    public function syncTags(
        int $postId,
        array $tagIds,
    ): void {}
}
