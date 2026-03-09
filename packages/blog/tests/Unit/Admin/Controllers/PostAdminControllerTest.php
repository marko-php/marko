<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Controllers;

use Closure;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Controllers\PostAdminController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Post\PostCreated;
use Marko\Blog\Events\Post\PostUpdated;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Repositories\TagRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\View\ViewInterface;
use ReflectionClass;

it('lists paginated posts on GET /admin/blog/posts with blog.posts.view permission', function (): void {
    $reflection = new ReflectionClass(PostAdminController::class);
    $method = $reflection->getMethod('index');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/posts');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.posts.view');

    // Test actual behavior - returns paginated posts
    $posts = [
        createTestPost(1, 'Post 1', 'post-1'),
        createTestPost(2, 'Post 2', 'post-2'),
    ];
    $capturedData = [];
    $controller = createController(
        posts: $posts,
        totalPosts: 2,
        capturedData: $capturedData,
    );

    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/post/index')
        ->and($capturedData)->toHaveKey('posts')
        ->and($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->items)->toHaveCount(2);
});

it('renders create form on GET /admin/blog/posts/create with blog.posts.create permission', function (): void {
    $reflection = new ReflectionClass(PostAdminController::class);
    $method = $reflection->getMethod('create');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/posts/create');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.posts.create');

    // Test actual behavior - returns form with authors, categories, tags
    $authors = [createTestAuthor(1, 'John Doe', 'john-doe')];
    $categories = [createTestCategory(1, 'Tech', 'tech')];
    $tags = [createTestTag(1, 'PHP', 'php')];
    $capturedData = [];
    $controller = createController(
        authors: $authors,
        categories: $categories,
        tags: $tags,
        capturedData: $capturedData,
    );

    $response = $controller->create();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/post/create')
        ->and($capturedData)->toHaveKey('authors')
        ->and($capturedData['authors'])->toHaveCount(1)
        ->and($capturedData)->toHaveKey('categories')
        ->and($capturedData['categories'])->toHaveCount(1)
        ->and($capturedData)->toHaveKey('tags')
        ->and($capturedData['tags'])->toHaveCount(1);
});

it('creates new post on POST /admin/blog/posts with valid data', function (): void {
    $reflection = new ReflectionClass(PostAdminController::class);
    $method = $reflection->getMethod('store');

    // Check route attribute
    $routeAttrs = $method->getAttributes(PostRoute::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/posts');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.posts.create');

    // Test actual behavior
    $savedEntities = [];
    $syncedCategories = [];
    $syncedTags = [];
    $capturedData = [];
    $controller = createController(
        capturedData: $capturedData,
        savedEntities: $savedEntities,
        syncedCategories: $syncedCategories,
        syncedTags: $syncedTags,
    );

    $request = new Request(post: [
        'title' => 'New Blog Post',
        'content' => 'This is the post content.',
        'summary' => 'A short summary.',
        'author_id' => '1',
        'category_ids' => [1, 2],
        'tag_ids' => [3, 4],
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0])->toBeInstanceOf(Post::class)
        ->and($savedEntities[0]->title)->toBe('New Blog Post')
        ->and($savedEntities[0]->content)->toBe('This is the post content.')
        ->and($savedEntities[0]->summary)->toBe('A short summary.')
        ->and($savedEntities[0]->authorId)->toBe(1);
});

it('returns validation errors on POST /admin/blog/posts with invalid data', function (): void {
    $savedEntities = [];
    $capturedData = [];
    $controller = createController(
        capturedData: $capturedData,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'title' => '',
        'content' => '',
        'author_id' => '0',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/post/create')
        ->and($capturedData)->toHaveKey('errors')
        ->and($capturedData['errors'])->toContain('Title is required')
        ->and($capturedData['errors'])->toContain('Content is required')
        ->and($capturedData['errors'])->toContain('Author is required')
        ->and($savedEntities)->toBeEmpty();
});

it('renders edit form on GET /admin/blog/posts/{id}/edit with blog.posts.edit permission', function (): void {
    $reflection = new ReflectionClass(PostAdminController::class);
    $method = $reflection->getMethod('edit');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/posts/{id}/edit');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.posts.edit');

    // Test actual behavior
    $post = createTestPost(1, 'Existing Post', 'existing-post');
    $authors = [createTestAuthor(1, 'John Doe', 'john-doe')];
    $categories = [createTestCategory(1, 'Tech', 'tech')];
    $tags = [createTestTag(1, 'PHP', 'php')];
    $capturedData = [];
    $controller = createController(
        findPost: $post,
        authors: $authors,
        categories: $categories,
        tags: $tags,
        categoriesForPost: $categories,
        tagsForPost: $tags,
        capturedData: $capturedData,
    );

    $response = $controller->edit(1);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/post/edit')
        ->and($capturedData)->toHaveKey('post')
        ->and($capturedData['post']->getTitle())->toBe('Existing Post')
        ->and($capturedData)->toHaveKey('authors')
        ->and($capturedData)->toHaveKey('categories')
        ->and($capturedData)->toHaveKey('tags')
        ->and($capturedData)->toHaveKey('postCategories')
        ->and($capturedData)->toHaveKey('postTags');
});

it('returns 404 when editing non-existent post', function (): void {
    $controller = createController();

    $response = $controller->edit(999);

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('updates post on PUT /admin/blog/posts/{id} with valid data', function (): void {
    $reflection = new ReflectionClass(PostAdminController::class);
    $method = $reflection->getMethod('update');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Put::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/posts/{id}');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.posts.edit');

    // Test actual behavior
    $existingPost = createTestPost(1, 'Old Title', 'old-title');
    $savedEntities = [];
    $syncedCategories = [];
    $syncedTags = [];
    $controller = createController(
        findPost: $existingPost,
        savedEntities: $savedEntities,
        syncedCategories: $syncedCategories,
        syncedTags: $syncedTags,
    );

    $request = new Request(post: [
        'title' => 'Updated Title',
        'content' => 'Updated content.',
        'summary' => 'Updated summary.',
        'author_id' => '2',
        'category_ids' => [1, 3],
        'tag_ids' => [2],
    ]);

    $response = $controller->update(1, $request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0]->title)->toBe('Updated Title')
        ->and($savedEntities[0]->content)->toBe('Updated content.')
        ->and($savedEntities[0]->summary)->toBe('Updated summary.')
        ->and($savedEntities[0]->authorId)->toBe(2);
});

it('deletes post on DELETE /admin/blog/posts/{id} with blog.posts.delete permission', function (): void {
    $reflection = new ReflectionClass(PostAdminController::class);
    $method = $reflection->getMethod('destroy');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Delete::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/posts/{id}');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.posts.delete');

    // Test actual behavior
    $existingPost = createTestPost(1, 'Post to Delete', 'post-to-delete');
    $deletedEntities = [];
    $controller = createController(
        findPost: $existingPost,
        deletedEntities: $deletedEntities,
    );

    $response = $controller->destroy(1);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($deletedEntities)->toHaveCount(1)
        ->and($deletedEntities[0]->id)->toBe(1);
});

it('publishes post on POST /admin/blog/posts/{id}/publish with blog.posts.publish permission', function (): void {
    $reflection = new ReflectionClass(PostAdminController::class);
    $method = $reflection->getMethod('publish');

    // Check route attribute
    $routeAttrs = $method->getAttributes(PostRoute::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/posts/{id}/publish');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.posts.publish');

    // Test actual behavior
    $draftPost = createTestPost(1, 'Draft Post', 'draft-post', PostStatus::Draft);
    $savedEntities = [];
    $controller = createController(
        findPost: $draftPost,
        savedEntities: $savedEntities,
    );

    $response = $controller->publish(1);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0]->status)->toBe(PostStatus::Published);
});

it('requires AdminAuthMiddleware on all routes', function (): void {
    $reflection = new ReflectionClass(PostAdminController::class);

    // Check class-level middleware attribute
    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);

    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);
});

it('syncs categories and tags on create and update', function (): void {
    // Test create syncs
    $savedEntities = [];
    $syncedCategories = [];
    $syncedTags = [];
    $controller = createController(
        savedEntities: $savedEntities,
        syncedCategories: $syncedCategories,
        syncedTags: $syncedTags,
    );

    $request = new Request(post: [
        'title' => 'Post with Relations',
        'content' => 'Content here.',
        'author_id' => '1',
        'category_ids' => [1, 2, 3],
        'tag_ids' => [4, 5],
    ]);

    $controller->store($request);

    expect($syncedCategories)->toHaveCount(1)
        ->and($syncedCategories[0]['categoryIds'])->toBe([1, 2, 3])
        ->and($syncedTags)->toHaveCount(1)
        ->and($syncedTags[0]['tagIds'])->toBe([4, 5]);

    // Test update syncs
    $existingPost = createTestPost(1, 'Existing', 'existing');
    $savedEntities2 = [];
    $syncedCategories2 = [];
    $syncedTags2 = [];
    $controller2 = createController(
        findPost: $existingPost,
        savedEntities: $savedEntities2,
        syncedCategories: $syncedCategories2,
        syncedTags: $syncedTags2,
    );

    $request2 = new Request(post: [
        'title' => 'Updated',
        'content' => 'Updated content.',
        'author_id' => '1',
        'category_ids' => [10, 20],
        'tag_ids' => [30],
    ]);

    $controller2->update(1, $request2);

    expect($syncedCategories2)->toHaveCount(1)
        ->and($syncedCategories2[0]['categoryIds'])->toBe([10, 20])
        ->and($syncedTags2)->toHaveCount(1)
        ->and($syncedTags2[0]['tagIds'])->toBe([30]);
});

it('dispatches PostCreated and PostUpdated events', function (): void {
    // Test PostCreated on store
    $dispatcher = new FakeEventDispatcher();
    $savedEntities = [];
    $controller = createController(
        savedEntities: $savedEntities,
        eventDispatcher: $dispatcher,
    );

    $request = new Request(post: [
        'title' => 'New Post',
        'content' => 'Content here.',
        'author_id' => '1',
    ]);

    $controller->store($request);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBeInstanceOf(PostCreated::class)
        ->and($dispatcher->dispatched[0]->getPost()->title)->toBe('New Post');

    // Test PostUpdated on update
    $existingPost = createTestPost(1, 'Existing', 'existing');
    $dispatcher2 = new FakeEventDispatcher();
    $savedEntities2 = [];
    $controller2 = createController(
        findPost: $existingPost,
        savedEntities: $savedEntities2,
        eventDispatcher: $dispatcher2,
    );

    $request2 = new Request(post: [
        'title' => 'Updated Post',
        'content' => 'Updated content.',
        'author_id' => '1',
    ]);

    $controller2->update(1, $request2);

    expect($dispatcher2->dispatched)->toHaveCount(1)
        ->and($dispatcher2->dispatched[0])->toBeInstanceOf(PostUpdated::class)
        ->and($dispatcher2->dispatched[0]->getPost()->title)->toBe('Updated Post');
});

// Helper functions

function createTestPost(
    int $id,
    string $title,
    string $slug,
    PostStatus $status = PostStatus::Draft,
    ?string $summary = null,
    ?string $publishedAt = null,
    int $authorId = 1,
): Post {
    $post = new Post(title: $title, content: 'Content', authorId: $authorId);
    $post->id = $id;
    $post->slug = $slug;
    $post->status = $status;
    $post->summary = $summary;
    $post->publishedAt = $publishedAt;
    $post->createdAt = '2024-01-01 12:00:00';

    return $post;
}

function createTestAuthor(
    int $id,
    string $name,
    string $slug,
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->email = 'test@example.com';
    $author->slug = $slug;

    return $author;
}

function createTestCategory(
    int $id,
    string $name,
    string $slug,
): Category {
    $category = new Category();
    $category->id = $id;
    $category->name = $name;
    $category->slug = $slug;

    return $category;
}

function createTestTag(
    int $id,
    string $name,
    string $slug,
): Tag {
    $tag = new Tag();
    $tag->id = $id;
    $tag->name = $name;
    $tag->slug = $slug;

    return $tag;
}

function createMockPostRepo(
    array $findAllResult = [],
    int $countAllResult = 0,
    ?Post $findResult = null,
    array $categoriesForPost = [],
    array $tagsForPost = [],
    array &$savedEntities = [],
    array &$deletedEntities = [],
    array &$syncedCategories = [],
    array &$syncedTags = [],
): PostRepositoryInterface {
    return new class (
        $findAllResult,
        $countAllResult,
        $findResult,
        $categoriesForPost,
        $tagsForPost,
        $savedEntities,
        $deletedEntities,
        $syncedCategories,
        $syncedTags,
    ) implements PostRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
            private int $countAllResult,
            private ?Post $findResult,
            private array $categoriesForPost,
            private array $tagsForPost,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$savedEntities,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$deletedEntities,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$syncedCategories,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$syncedTags,
        ) {}

        public function find(
            int $id,
        ): ?Entity {
            return $this->findResult;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            if ($this->findResult === null) {
                throw RepositoryException::entityNotFound(Post::class, $id);
            }

            return $this->findResult;
        }

        public function findAll(): array
        {
            return $this->findAllResult;
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

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function save(
            Entity $entity,
        ): void {
            if ($entity instanceof Post && $entity->id === null) {
                $entity->id = 99;
            }
            $this->savedEntities[] = $entity;
        }

        public function delete(
            Entity $entity,
        ): void {
            $this->deletedEntities[] = $entity;
        }

        public function findBySlug(
            string $slug,
        ): ?Post {
            return $this->findResult;
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

        public function findScheduledPostsDue(): array
        {
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
            return $this->categoriesForPost;
        }

        public function getTagsForPost(
            int $postId,
        ): array {
            return $this->tagsForPost;
        }

        public function syncCategories(
            int $postId,
            array $categoryIds,
        ): void {
            $this->syncedCategories[] = ['postId' => $postId, 'categoryIds' => $categoryIds];
        }

        public function syncTags(
            int $postId,
            array $tagIds,
        ): void {
            $this->syncedTags[] = ['postId' => $postId, 'tagIds' => $tagIds];
        }
    };
}

function createMockAuthorRepo(
    ?Author $findResult = null,
    array $findAllResult = [],
): AuthorRepositoryInterface {
    return new class ($findResult, $findAllResult) implements AuthorRepositoryInterface
    {
        public function __construct(
            private ?Author $findResult,
            private array $findAllResult,
        ) {}

        public function find(
            int $id,
        ): ?Entity {
            return $this->findResult;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            if ($this->findResult === null) {
                throw RepositoryException::entityNotFound(Author::class, $id);
            }

            return $this->findResult;
        }

        public function findAll(): array
        {
            return $this->findAllResult;
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

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}

        public function findBySlug(
            string $slug,
        ): ?Author {
            return $this->findResult;
        }

        public function findByEmail(
            string $email,
        ): ?Author {
            return $this->findResult;
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }
    };
}

function createMockCategoryRepo(
    array $findAllResult = [],
): CategoryRepositoryInterface {
    return new class ($findAllResult) implements CategoryRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
        ) {}

        public function find(
            int $id,
        ): ?Entity {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::entityNotFound(Category::class, $id);
        }

        public function findAll(): array
        {
            return $this->findAllResult;
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

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}

        public function findBySlug(
            string $slug,
        ): ?Category {
            return null;
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function findChildren(
            Category $parent,
        ): array {
            return [];
        }

        public function getPath(
            Category $category,
        ): array {
            return [$category];
        }

        public function findRoots(): array
        {
            return [];
        }

        public function getPostsForCategory(
            int $categoryId,
        ): array {
            return [];
        }

        public function getDescendantIds(
            int $categoryId,
        ): array {
            return [];
        }
    };
}

function createMockTagRepo(
    array $findAllResult = [],
): TagRepositoryInterface {
    return new class ($findAllResult) implements TagRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
        ) {}

        public function find(
            int $id,
        ): ?Entity {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::entityNotFound(Tag::class, $id);
        }

        public function findAll(): array
        {
            return $this->findAllResult;
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

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function save(Entity $entity): void {}

        public function delete(Entity $entity): void {}

        public function findBySlug(
            string $slug,
        ): ?Tag {
            return null;
        }

        public function findByNameLike(
            string $name,
        ): array {
            return [];
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function getPostsForTag(
            int $tagId,
        ): array {
            return [];
        }
    };
}

function createMockPagination(
    array $items = [],
    int $totalItems = 0,
): PaginationServiceInterface {
    return new class ($items, $totalItems) implements PaginationServiceInterface
    {
        public function __construct(
            private array $items,
            private int $totalItems,
        ) {}

        public function paginate(
            array $items,
            int $totalItems,
            int $currentPage,
            ?int $perPage = null,
        ): PaginatedResult {
            return new PaginatedResult(
                items: $items,
                currentPage: $currentPage,
                totalItems: $totalItems,
                perPage: $perPage ?? 10,
                totalPages: $totalItems > 0 ? (int) ceil($totalItems / ($perPage ?? 10)) : 0,
                hasPreviousPage: $currentPage > 1,
                hasNextPage: $currentPage < (int) ceil($totalItems / ($perPage ?? 10)),
                pageNumbers: range(1, max(1, (int) ceil($totalItems / ($perPage ?? 10)))),
            );
        }

        public function calculateOffset(
            int $page,
            ?int $perPage = null,
        ): int {
            return ($page - 1) * ($perPage ?? 10);
        }

        public function getPerPage(): int
        {
            return 10;
        }
    };
}

function createMockSlugGenerator(): SlugGeneratorInterface
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

function createMockAdminView(
    array &$capturedData = [],
): ViewInterface {
    return new class ($capturedData) implements ViewInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$capturedData,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            $this->capturedData = $data;

            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            $this->capturedData = $data;

            return "rendered: $template";
        }
    };
}

function createController(
    array $posts = [],
    int $totalPosts = 0,
    ?Post $findPost = null,
    array $authors = [],
    array $categories = [],
    array $tags = [],
    array $categoriesForPost = [],
    array $tagsForPost = [],
    array &$capturedData = [],
    array &$savedEntities = [],
    array &$deletedEntities = [],
    array &$syncedCategories = [],
    array &$syncedTags = [],
    ?FakeEventDispatcher $eventDispatcher = null,
): PostAdminController {
    return new PostAdminController(
        postRepository: createMockPostRepo(
            findAllResult: $posts,
            countAllResult: $totalPosts,
            findResult: $findPost,
            categoriesForPost: $categoriesForPost,
            tagsForPost: $tagsForPost,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
            syncedCategories: $syncedCategories,
            syncedTags: $syncedTags,
        ),
        authorRepository: createMockAuthorRepo(
            findAllResult: $authors,
        ),
        categoryRepository: createMockCategoryRepo(
            findAllResult: $categories,
        ),
        tagRepository: createMockTagRepo(
            findAllResult: $tags,
        ),
        paginationService: createMockPagination($posts, $totalPosts),
        slugGenerator: createMockSlugGenerator(),
        eventDispatcher: $eventDispatcher ?? new FakeEventDispatcher(),
        view: createMockAdminView($capturedData),
    );
}
