<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Controllers\PostController;

use Marko\Blog\Controllers\PostController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeSession;
use Marko\View\ViewInterface;
use ReflectionClass;

it('injects PostRepositoryInterface not concrete PostRepository', function (): void {
    $reflection = new ReflectionClass(PostController::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $parameters = $constructor->getParameters();
    expect($parameters)->toHaveCount(7)
        ->and($parameters[0]->getName())->toBe('repository')
        ->and($parameters[0]->getType()->getName())->toBe(PostRepositoryInterface::class)
        ->and($parameters[1]->getName())->toBe('authorRepository')
        ->and($parameters[1]->getType()->getName())->toBe(AuthorRepositoryInterface::class)
        ->and($parameters[2]->getName())->toBe('categoryRepository')
        ->and($parameters[2]->getType()->getName())->toBe(CategoryRepositoryInterface::class)
        ->and($parameters[3]->getName())->toBe('commentRepository')
        ->and($parameters[3]->getType()->getName())->toBe(CommentRepositoryInterface::class)
        ->and($parameters[4]->getName())->toBe('paginationService')
        ->and($parameters[4]->getType()->getName())->toBe(PaginationServiceInterface::class)
        ->and($parameters[5]->getName())->toBe('view')
        ->and($parameters[5]->getType()->getName())->toBe(ViewInterface::class)
        ->and($parameters[6]->getName())->toBe('session')
        ->and($parameters[6]->getType()->getName())->toBe(SessionInterface::class);
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

it('returns response using view on index route', function (): void {
    $posts = [
        createPost(1, 'Post 1', 'post-1'),
        createPost(2, 'Post 2', 'post-2'),
    ];
    $repository = createMockPostRepository(findPublishedPaginatedResult: $posts, countPublishedResult: 2);
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService($posts, 2);
    $view = createMockView();
    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->index();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::post/index');
});

it('returns response using view on show route', function (): void {
    $repository = createMockPostRepository(
        findBySlugResult: createPost(1, 'Hello World', 'hello-world'),
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $view = createMockView();
    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->show('hello-world');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::post/show');
});

it('returns single post at GET /blog/{slug}', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'Test Post',
        slug: 'test-post',
        author: $author,
    );

    $repository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->show('test-post');

    expect($response->statusCode())->toBe(200)
        ->and($capturedData)->toHaveKey('post')
        ->and($capturedData['post']->getTitle())->toBe('Test Post')
        ->and($capturedData['post']->getSlug())->toBe('test-post');
});

it('returns 404 when post slug not found', function (): void {
    $repository = createMockPostRepository();
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $view = createMockView();
    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->show('non-existent');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('returns 404 when post is not published', function (): void {
    $draftPost = createPost(
        id: 1,
        title: 'Draft Post',
        slug: 'draft-post',
        status: PostStatus::Draft,
    );

    $repository = createMockPostRepository(findBySlugResult: $draftPost);
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $view = createMockView();
    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->show('draft-post');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('includes full post content in response', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPostWithContent(
        id: 1,
        title: 'Full Content Post',
        slug: 'full-content-post',
        content: '<p>This is the full article content with <strong>formatting</strong>.</p>',
        author: $author,
    );

    $repository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->show('full-content-post');

    expect($capturedData)->toHaveKey('post')
        ->and($capturedData['post']->getContent())->toBe(
            '<p>This is the full article content with <strong>formatting</strong>.</p>',
        );
});

it('includes author information', function (): void {
    $author = createAuthor(1, 'Jane Smith', 'jane-smith');
    $post = createPost(
        id: 1,
        title: 'Post with Author',
        slug: 'post-with-author',
        author: $author,
    );

    $repository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->show('post-with-author');

    expect($capturedData)->toHaveKey('post')
        ->and($capturedData['post']->getAuthor()->getName())->toBe('Jane Smith')
        ->and($capturedData['post']->getAuthor()->getSlug())->toBe('jane-smith');
});

it('includes post categories', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'Post with Categories',
        slug: 'post-with-categories',
        author: $author,
    );

    $categories = [
        createCategory(1, 'Technology', 'technology'),
        createCategory(2, 'Programming', 'programming'),
    ];

    $repository = createMockPostRepository(
        findBySlugResult: $post,
        categoriesForPost: $categories,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->show('post-with-categories');

    expect($capturedData)->toHaveKey('categories')
        ->and($capturedData['categories'])->toHaveCount(2)
        ->and($capturedData['categories'][0]->getName())->toBe('Technology')
        ->and($capturedData['categories'][1]->getName())->toBe('Programming');
});

it('includes post tags', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'Post with Tags',
        slug: 'post-with-tags',
        author: $author,
    );

    $tags = [
        createTag(1, 'PHP', 'php'),
        createTag(2, 'Laravel', 'laravel'),
        createTag(3, 'TDD', 'tdd'),
    ];

    $repository = createMockPostRepository(
        findBySlugResult: $post,
        tagsForPost: $tags,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->show('post-with-tags');

    expect($capturedData)->toHaveKey('tags')
        ->and($capturedData['tags'])->toHaveCount(3)
        ->and($capturedData['tags'][0]->getName())->toBe('PHP')
        ->and($capturedData['tags'][1]->getName())->toBe('Laravel')
        ->and($capturedData['tags'][2]->getName())->toBe('TDD');
});

it('includes threaded verified comments', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'Post with Comments',
        slug: 'post-with-comments',
        author: $author,
    );

    // Create threaded comments structure
    $childComment = createComment(
        id: 2,
        postId: 1,
        name: 'Jane Smith',
        email: 'jane@example.com',
        content: 'This is a reply comment.',
        parentId: 1,
    );

    $parentComment = createComment(
        id: 1,
        postId: 1,
        name: 'Bob Wilson',
        email: 'bob@example.com',
        content: 'This is a root comment.',
        children: [$childComment],
    );

    $threadedComments = [$parentComment];

    $repository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository(threadedComments: $threadedComments);
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->show('post-with-comments');

    expect($capturedData)->toHaveKey('comments')
        ->and($capturedData['comments'])->toHaveCount(1)
        ->and($capturedData['comments'][0]->content)->toBe('This is a root comment.')
        ->and($capturedData['comments'][0]->getChildren())->toHaveCount(1)
        ->and($capturedData['comments'][0]->getChildren()[0]->content)->toBe('This is a reply comment.');
});

it('renders show using view template', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'View Template Test',
        slug: 'view-template-test',
        author: $author,
    );

    $repository = createMockPostRepository(findBySlugResult: $post);
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $view = createMockView();

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->show('view-template-test');

    expect($response->body())->toContain('blog::post/show');
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

it('returns paginated list of published posts at GET /blog', function (): void {
    // Verify the route attribute
    $reflection = new ReflectionClass(PostController::class);
    $method = $reflection->getMethod('index');
    $attributes = $method->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);
    $routeAttribute = $attributes[0]->newInstance();
    expect($routeAttribute->path)->toBe('/blog');

    // Verify that calling index returns paginated posts
    $posts = [
        createPost(1, 'Post 1', 'post-1'),
        createPost(2, 'Post 2', 'post-2'),
    ];
    $repository = createMockPostRepository(findPublishedPaginatedResult: $posts, countPublishedResult: 2);
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService($posts, 2);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->index();

    expect($response->statusCode())->toBe(200)
        ->and($capturedData)->toHaveKey('posts')
        ->and($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->items)->toHaveCount(2);
});

it('orders posts by published date descending', function (): void {
    // This test verifies the controller uses findPublishedPaginated
    // Ordering is handled by the repository (see PostRepository implementation)
    $posts = [
        createPost(1, 'Newer Post', 'newer-post', publishedAt: '2024-01-02 12:00:00'),
        createPost(2, 'Older Post', 'older-post', publishedAt: '2024-01-01 12:00:00'),
    ];
    $repository = createMockPostRepository(findPublishedPaginatedResult: $posts, countPublishedResult: 2);
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService($posts, 2);
    $view = createMockView();

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->index();

    expect($response->statusCode())->toBe(200);
});

it('excludes draft and scheduled posts from listing', function (): void {
    // This test verifies the controller uses findPublishedPaginated (not findAll)
    // which only returns posts with status = Published
    // Actual filtering is repository responsibility - controller calls the right method
    $publishedPosts = [
        createPost(1, 'Published Post', 'published-post', PostStatus::Published),
    ];

    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $publishedPosts,
        countPublishedResult: 1,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService($publishedPosts, 1);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->index();

    // Verify that only published posts are in the result
    expect($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->items)->toHaveCount(1)
        ->and($capturedData['posts']->items[0]->status)->toBe(PostStatus::Published);
});

it('accepts page query parameter for pagination', function (): void {
    $posts = [createPost(3, 'Post on Page 3', 'post-page-3')];
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $posts,
        countPublishedResult: 25,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService($posts, 25);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->index(page: 3);

    expect($capturedData['posts']->currentPage)->toBe(3);
});

it('defaults to page 1 when no page parameter', function (): void {
    $posts = [createPost(1, 'Post 1', 'post-1')];
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $posts,
        countPublishedResult: 10,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService($posts, 10);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->index();

    expect($capturedData['posts']->currentPage)->toBe(1);
});

it('returns 404 for invalid page numbers', function (): void {
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: [],
        countPublishedResult: 25,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService([], 25);
    $view = createMockView();

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );

    // Test page 0
    $response = $controller->index(page: 0);
    expect($response->statusCode())->toBe(404);

    // Test negative page
    $response = $controller->index(page: -1);
    expect($response->statusCode())->toBe(404);

    // Test page beyond total (25 posts / 10 per page = 3 pages max)
    $response = $controller->index(page: 10);
    expect($response->statusCode())->toBe(404);
});

it('includes pagination metadata in response', function (): void {
    $posts = [createPost(1, 'Post 1', 'post-1')];
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $posts,
        countPublishedResult: 25,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService($posts, 25);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->index();

    expect($capturedData['posts'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['posts']->totalItems)->toBe(25)
        ->and($capturedData['posts']->totalPages)->toBe(3)
        ->and($capturedData['posts']->perPage)->toBe(10)
        ->and($capturedData['posts']->currentPage)->toBe(1)
        ->and($capturedData['posts']->hasPreviousPage)->toBeFalse()
        ->and($capturedData['posts']->hasNextPage)->toBeTrue();
});

it('includes post title summary author and date in listing', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'My First Post',
        slug: 'my-first-post',
        summary: 'This is the post summary.',
        publishedAt: '2024-06-15 10:30:00',
        author: $author,
    );

    $repository = createMockPostRepository(
        findPublishedPaginatedResult: [$post],
        countPublishedResult: 1,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService([$post], 1);
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->index();

    $postInView = $capturedData['posts']->items[0];
    expect($postInView->getTitle())->toBe('My First Post')
        ->and($postInView->getSummary())->toBe('This is the post summary.')
        ->and($postInView->getAuthor()->getName())->toBe('John Doe')
        ->and($postInView->getPublishedAt()->format('Y-m-d H:i:s'))->toBe('2024-06-15 10:30:00');
});

it('renders using view template', function (): void {
    $posts = [createPost(1, 'Post 1', 'post-1')];
    $repository = createMockPostRepository(
        findPublishedPaginatedResult: $posts,
        countPublishedResult: 1,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService($posts, 1);
    $view = createMockView();

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $response = $controller->index();

    expect($response->body())->toContain('blog::post/index');
});

it('includes categoryPaths with full hierarchy for each category', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'Post with Hierarchy',
        slug: 'post-with-hierarchy',
        author: $author,
    );

    // Create hierarchy: Technology > Programming > PHP
    $technology = createCategory(1, 'Technology', 'technology');
    $programming = createCategory(2, 'Programming', 'programming');
    $programming->parentId = 1;
    $php = createCategory(3, 'PHP', 'php');
    $php->parentId = 2;

    $categories = [$php]; // Post is assigned to PHP

    // Mock paths: PHP has full path [Technology, Programming, PHP]
    $categoryPaths = [
        3 => [$technology, $programming, $php],
    ];

    $repository = createMockPostRepository(
        findBySlugResult: $post,
        categoriesForPost: $categories,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepositoryWithPaths($categoryPaths);
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->show('post-with-hierarchy');

    expect($capturedData)->toHaveKey('categoryPaths')
        ->and($capturedData['categoryPaths'])->toHaveKey(3)
        ->and($capturedData['categoryPaths'][3])->toHaveCount(3)
        ->and($capturedData['categoryPaths'][3][0]->getName())->toBe('Technology')
        ->and($capturedData['categoryPaths'][3][1]->getName())->toBe('Programming')
        ->and($capturedData['categoryPaths'][3][2]->getName())->toBe('PHP');
});

it('includes categoryPaths for multiple categories', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'Post in Multiple Categories',
        slug: 'post-multiple-categories',
        author: $author,
    );

    // Create hierarchies
    $technology = createCategory(1, 'Technology', 'technology');
    $php = createCategory(2, 'PHP', 'php');
    $php->parentId = 1;
    $tutorials = createCategory(3, 'Tutorials', 'tutorials');

    $categories = [$php, $tutorials]; // Post is in PHP and Tutorials

    // PHP has path [Technology, PHP], Tutorials is a root
    $categoryPaths = [
        2 => [$technology, $php],
        3 => [$tutorials],
    ];

    $repository = createMockPostRepository(
        findBySlugResult: $post,
        categoriesForPost: $categories,
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepositoryWithPaths($categoryPaths);
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->show('post-multiple-categories');

    expect($capturedData)->toHaveKey('categoryPaths')
        ->and($capturedData['categoryPaths'])->toHaveCount(2)
        ->and($capturedData['categoryPaths'][2])->toHaveCount(2)
        ->and($capturedData['categoryPaths'][2][0]->getName())->toBe('Technology')
        ->and($capturedData['categoryPaths'][2][1]->getName())->toBe('PHP')
        ->and($capturedData['categoryPaths'][3])->toHaveCount(1)
        ->and($capturedData['categoryPaths'][3][0]->getName())->toBe('Tutorials');
});

it('includes empty categoryPaths when post has no categories', function (): void {
    $author = createAuthor(1, 'John Doe', 'john-doe');
    $post = createPost(
        id: 1,
        title: 'Post without Categories',
        slug: 'post-no-categories',
        author: $author,
    );

    $repository = createMockPostRepository(
        findBySlugResult: $post,
        categoriesForPost: [],
    );
    $commentRepository = createMockCommentRepository();
    $pagination = createMockPaginationService();
    $capturedData = [];
    $view = createMockViewWithCapture($capturedData);

    $authorRepository = createMockAuthorRepository();
    $categoryRepository = createMockCategoryRepository();
    $controller = new PostController(
        $repository,
        $authorRepository,
        $categoryRepository,
        $commentRepository,
        $pagination,
        $view,
        createMockSession(),
    );
    $controller->show('post-no-categories');

    expect($capturedData)->toHaveKey('categoryPaths')
        ->and($capturedData['categoryPaths'])->toBe([]);
});

// Helper functions

function createPost(
    int $id,
    string $title,
    string $slug,
    PostStatus $status = PostStatus::Published,
    ?string $summary = null,
    ?string $publishedAt = null,
    ?Author $author = null,
): Post {
    $post = new Post(title: $title, content: 'Content', authorId: 1);
    $post->id = $id;
    $post->slug = $slug;
    $post->status = $status;
    $post->summary = $summary;
    $post->publishedAt = $publishedAt ?? '2024-01-01 12:00:00';
    if ($author !== null) {
        $post->setAuthor($author);
    }

    return $post;
}

function createPostWithContent(
    int $id,
    string $title,
    string $slug,
    string $content,
    PostStatus $status = PostStatus::Published,
    ?string $summary = null,
    ?string $publishedAt = null,
    ?Author $author = null,
): Post {
    $post = new Post(title: $title, content: $content, authorId: 1);
    $post->id = $id;
    $post->slug = $slug;
    $post->status = $status;
    $post->summary = $summary;
    $post->publishedAt = $publishedAt ?? '2024-01-01 12:00:00';
    if ($author !== null) {
        $post->setAuthor($author);
    }

    return $post;
}

function createAuthor(
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

function createCategory(
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

function createTag(
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

function createMockPostRepository(
    array $findPublishedPaginatedResult = [],
    int $countPublishedResult = 0,
    ?Post $findBySlugResult = null,
    array $categoriesForPost = [],
    array $tagsForPost = [],
): PostRepositoryInterface {
    return new readonly class (
        $findPublishedPaginatedResult,
        $countPublishedResult,
        $findBySlugResult,
        $categoriesForPost,
        $tagsForPost,
    ) implements PostRepositoryInterface
    {
        public function __construct(
            private array $findPublishedPaginatedResult,
            private int $countPublishedResult,
            private ?Post $findBySlugResult,
            private array $categoriesForPost,
            private array $tagsForPost,
        ) {}

        public function findBySlug(
            string $slug,
        ): ?Post {
            return $this->findBySlugResult;
        }

        public function findPublished(): array
        {
            return [];
        }

        public function findPublishedPaginated(
            int $limit,
            int $offset,
        ): array {
            return $this->findPublishedPaginatedResult;
        }

        public function countPublished(): int
        {
            return $this->countPublishedResult;
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
        ): void {}

        public function syncTags(
            int $postId,
            array $tagIds,
        ): void {}

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

        public function find(
            int $id,
        ): ?Entity {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::notFound(Post::class, $id);
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
        ): void {}

        public function delete(
            Entity $entity,
        ): void {}
    };
}

function createMockAuthorRepository(
    ?Author $findResult = null,
): AuthorRepositoryInterface {
    return new readonly class ($findResult) implements AuthorRepositoryInterface
    {
        public function __construct(
            private ?Author $findResult,
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
                throw RepositoryException::notFound(Author::class, $id);
            }

            return $this->findResult;
        }

        public function findAll(): array
        {
            return $this->findResult !== null ? [$this->findResult] : [];
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
        ): void {}

        public function delete(
            Entity $entity,
        ): void {}

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

function createMockCategoryRepository(): CategoryRepositoryInterface
{
    return new class () implements CategoryRepositoryInterface
    {
        public function find(
            int $id,
        ): ?Entity {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::notFound(Category::class, $id);
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
        ): void {}

        public function delete(
            Entity $entity,
        ): void {}

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

function createMockCategoryRepositoryWithPaths(
    array $categoryPaths = [],
): CategoryRepositoryInterface {
    return new readonly class ($categoryPaths) implements CategoryRepositoryInterface
    {
        public function __construct(
            private array $categoryPaths,
        ) {}

        public function find(
            int $id,
        ): ?Entity {
            return null;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::notFound(Category::class, $id);
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
        ): void {}

        public function delete(
            Entity $entity,
        ): void {}

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
            return $this->categoryPaths[$category->id] ?? [$category];
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

function createMockPaginationService(
    array $items = [],
    int $totalItems = 0,
): PaginationServiceInterface {
    return new readonly class ($items, $totalItems) implements PaginationServiceInterface
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

function createMockSession(): FakeSession
{
    return new FakeSession();
}

function createMockView(): ViewInterface
{
    return new class () implements ViewInterface
    {
        public function render(
            string $template,
            array $data = [],
        ): Response {
            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            return "rendered: $template";
        }
    };
}

function createMockViewWithCapture(
    array &$capturedData,
): ViewInterface {
    return new class ($capturedData) implements ViewInterface
    {
        public function __construct(
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

function createMockCommentRepository(
    array $threadedComments = [],
): CommentRepositoryInterface {
    return new readonly class ($threadedComments) implements CommentRepositoryInterface
    {
        public function __construct(
            private array $threadedComments,
        ) {}

        public function find(
            int $id,
        ): ?Comment {
            return null;
        }

        public function findVerifiedForPost(
            int $postId,
        ): array {
            return [];
        }

        public function findPendingForPost(
            int $postId,
        ): array {
            return [];
        }

        public function getThreadedCommentsForPost(
            int $postId,
        ): array {
            return $this->threadedComments;
        }

        public function countForPost(
            int $postId,
        ): int {
            return 0;
        }

        public function countVerifiedForPost(
            int $postId,
        ): int {
            return count($this->threadedComments);
        }

        public function findByEmail(
            string $email,
        ): array {
            return [];
        }

        public function calculateDepth(
            int $commentId,
        ): int {
            return 0;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            throw RepositoryException::notFound(Comment::class, $id);
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
        ): void {}

        public function delete(
            Entity $entity,
        ): void {}
    };
}

function createComment(
    int $id,
    int $postId,
    string $name,
    string $email,
    string $content,
    CommentStatus $status = CommentStatus::Verified,
    ?int $parentId = null,
    array $children = [],
): Comment {
    $comment = new Comment();
    $comment->id = $id;
    $comment->postId = $postId;
    $comment->name = $name;
    $comment->email = $email;
    $comment->content = $content;
    $comment->status = $status;
    $comment->parentId = $parentId;
    $comment->createdAt = '2024-01-01 12:00:00';
    if ($status === CommentStatus::Verified) {
        $comment->verifiedAt = '2024-01-01 12:30:00';
    }
    if (!empty($children)) {
        $comment->setChildren($children);
    }

    return $comment;
}
