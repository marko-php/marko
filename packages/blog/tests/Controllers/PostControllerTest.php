<?php

declare(strict_types=1);

use Marko\Blog\Controllers\PostController;
use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\PostRepository;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

it('injects PostRepository via constructor', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $parameters = $constructor->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('repository')
        ->and($parameters[0]->getType()->getName())->toBe(PostRepository::class);
});

it('has GET /blog route on index method', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $method = $reflection->getMethod('index');
    $attributes = $method->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    expect($routeAttribute->path)->toBe('/blog');
});

it('has GET /blog/{slug} route on show method', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $method = $reflection->getMethod('show');
    $attributes = $method->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    expect($routeAttribute->path)->toBe('/blog/{slug}');
});

it('returns response with all posts data on index route', function (): void {
    $repository = createMockPostRepository([
        ['id' => 1, 'title' => 'Post 1'],
        ['id' => 2, 'title' => 'Post 2'],
    ]);
    $controller = new PostController($repository);
    $response = $controller->index();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('2');
});

it('returns response with single post data on show route', function (): void {
    $repository = createMockPostRepository(
        posts: [],
        findBySlugResult: ['id' => 1, 'title' => 'Hello World', 'slug' => 'hello-world'],
    );
    $controller = new PostController($repository);
    $response = $controller->show('hello-world');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('Hello World');
});

it('returns 404 response when post slug not found', function (): void {
    $repository = createMockPostRepository(
        posts: [],
        findBySlugResult: null,
    );
    $controller = new PostController($repository);
    $response = $controller->show('non-existent');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('maintains existing route attributes for GET /blog and GET /blog/{slug}', function (): void {
    $reflection = new ReflectionClass(PostController::class);

    // Check index method route
    $indexMethod = $reflection->getMethod('index');
    $indexAttributes = $indexMethod->getAttributes(Get::class);
    expect($indexAttributes)->toHaveCount(1);
    $indexRoute = $indexAttributes[0]->newInstance();
    expect($indexRoute->path)->toBe('/blog');

    // Check show method route
    $showMethod = $reflection->getMethod('show');
    $showAttributes = $showMethod->getAttributes(Get::class);
    expect($showAttributes)->toHaveCount(1);
    $showRoute = $showAttributes[0]->newInstance();
    expect($showRoute->path)->toBe('/blog/{slug}');
});

// Helper function to create mock PostRepository

function createMockPostRepository(
    array $posts = [],
    ?array $findBySlugResult = null,
): PostRepository {
    // Create mock Post entities from data
    $postEntities = array_map(function (array $data): Post {
        $post = new Post();
        $post->id = $data['id'] ?? null;
        $post->title = $data['title'] ?? '';
        $post->slug = $data['slug'] ?? '';
        $post->content = $data['content'] ?? '';
        $post->createdAt = $data['created_at'] ?? null;
        $post->updatedAt = $data['updated_at'] ?? null;

        return $post;
    }, $posts);

    $findBySlugEntity = null;
    if ($findBySlugResult !== null) {
        $findBySlugEntity = new Post();
        $findBySlugEntity->id = $findBySlugResult['id'] ?? null;
        $findBySlugEntity->title = $findBySlugResult['title'] ?? '';
        $findBySlugEntity->slug = $findBySlugResult['slug'] ?? '';
        $findBySlugEntity->content = $findBySlugResult['content'] ?? '';
        $findBySlugEntity->createdAt = $findBySlugResult['created_at'] ?? null;
        $findBySlugEntity->updatedAt = $findBySlugResult['updated_at'] ?? null;
    }

    // Create an anonymous class extending PostRepository but overriding methods
    // Since PostRepository extends Repository which requires connection/metadata,
    // we need to bypass the constructor
    return new class ($postEntities, $findBySlugEntity) extends PostRepository
    {
        public function __construct(
            private readonly array $postEntities,
            private readonly ?Post $findBySlugEntity,
        ) {
            // Skip parent constructor - we're mocking everything
        }

        public function findAll(): array
        {
            return $this->postEntities;
        }

        public function findBySlug(
            string $slug,
        ): ?Post {
            return $this->findBySlugEntity;
        }
    };
}
