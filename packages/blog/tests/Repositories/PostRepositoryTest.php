<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\PostRepository;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

it('extends the Repository base class', function (): void {
    $reflection = new ReflectionClass(PostRepository::class);

    expect($reflection->isSubclassOf(Repository::class))->toBeTrue();
});

it('defines ENTITY_CLASS constant pointing to Post entity', function (): void {
    $reflection = new ReflectionClass(PostRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(Post::class);
});

it('can find a post by id using inherited find method', function (): void {
    $connection = createMockConnection([
        [
            'id' => 1,
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is test content',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $post = $repository->find(1);

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->id)->toBe(1)
        ->and($post->title)->toBe('Test Post')
        ->and($post->slug)->toBe('test-post')
        ->and($post->content)->toBe('This is test content');
});

it('can find all posts using inherited findAll method', function (): void {
    $connection = createMockConnection([
        [
            'id' => 1,
            'title' => 'First Post',
            'slug' => 'first-post',
            'content' => 'First content',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'title' => 'Second Post',
            'slug' => 'second-post',
            'content' => 'Second content',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => '2024-01-02 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $posts = $repository->findAll();

    expect($posts)->toHaveCount(2)
        ->and($posts[0])->toBeInstanceOf(Post::class)
        ->and($posts[0]->title)->toBe('First Post')
        ->and($posts[1]->title)->toBe('Second Post');
});

it('can find posts by criteria using inherited findBy method', function (): void {
    $connection = createMockConnection([
        [
            'id' => 1,
            'title' => 'Matching Post',
            'slug' => 'matching-post',
            'content' => 'Content here',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $posts = $repository->findBy(['title' => 'Matching Post']);

    expect($posts)->toHaveCount(1)
        ->and($posts[0])->toBeInstanceOf(Post::class)
        ->and($posts[0]->title)->toBe('Matching Post');
});

it('provides findBySlug convenience method for slug lookups', function (): void {
    $connection = createMockConnection([
        [
            'id' => 1,
            'title' => 'My Post',
            'slug' => 'my-post',
            'content' => 'Content here',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new PostRepository($connection, $metadataFactory, $hydrator);

    $post = $repository->findBySlug('my-post');

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->slug)->toBe('my-post')
        ->and($post->title)->toBe('My Post');
});

// Helper function to create mock connection

function createMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return new class ($queryResult) implements ConnectionInterface
    {
        public function __construct(
            private readonly array $queryResult,
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
            return 1;
        }
    };
}
