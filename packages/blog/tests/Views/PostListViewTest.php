<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Views\PostListView;

use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Enum\PostStatus;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;

describe('Post List View', function (): void {
    it('renders list of posts', function (): void {
        $view = createPostListTestView();

        $posts = [
            createPostListPost(1, 'First Post', 'first-post'),
            createPostListPost(2, 'Second Post', 'second-post'),
        ];
        $pagination = createPostListPaginatedResult($posts);

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toContain('First Post')
            ->and($html)->toContain('Second Post');
    });

    it('displays post title as link to full post', function (): void {
        $view = createPostListTestView();

        $post = createPostListPost(1, 'My Amazing Post', 'my-amazing-post');
        $pagination = createPostListPaginatedResult([$post]);

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<a[^>]*href\s*=\s*["\']\/blog\/my-amazing-post["\']/i')
            ->and($html)->toMatch('/<a[^>]*>.*My Amazing Post.*<\/a>/s');
    });

    it('displays post summary', function (): void {
        $view = createPostListTestView();

        $post = createPostListPost(
            1,
            'Test Post',
            'test-post',
            'This is a brief summary of the post content.',
        );
        $pagination = createPostListPaginatedResult([$post]);

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toContain('This is a brief summary of the post content.');
    });

    it('displays author name as link to author archive', function (): void {
        $view = createPostListTestView();

        $author = createPostListAuthor(1, 'Jane Smith', 'jane-smith');
        $post = createPostListPost(1, 'Test Post', 'test-post');
        $post->setAuthor($author);
        $pagination = createPostListPaginatedResult([$post]);

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<a[^>]*href\s*=\s*["\']\/blog\/author\/jane-smith["\']/i')
            ->and($html)->toContain('Jane Smith');
    });

    it('displays published date', function (): void {
        $view = createPostListTestView();

        $post = createPostListPost(1, 'Test Post', 'test-post', null, '2025-03-15 14:30:00');
        $pagination = createPostListPaginatedResult([$post]);

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<time[^>]*datetime\s*=\s*["\']2025-03-15["\']/i')
            ->and($html)->toMatch('/March\s+15,?\s+2025|Mar\s+15/i');
    });

    it('displays post categories as links', function (): void {
        $view = createPostListTestView();

        $categories = [
            createPostListCategory(1, 'Technology', 'technology'),
            createPostListCategory(2, 'Programming', 'programming'),
        ];
        $post = createPostListPost(1, 'Test Post', 'test-post');
        $post->setCategories($categories);
        $pagination = createPostListPaginatedResult([$post]);

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<a[^>]*href\s*=\s*["\']\/blog\/category\/technology["\']/i')
            ->and($html)->toContain('Technology')
            ->and($html)->toMatch('/<a[^>]*href\s*=\s*["\']\/blog\/category\/programming["\']/i')
            ->and($html)->toContain('Programming');
    });

    it('displays post tags as links', function (): void {
        $view = createPostListTestView();

        $tags = [
            createPostListTag(1, 'PHP', 'php'),
            createPostListTag(2, 'Laravel', 'laravel'),
        ];
        $post = createPostListPost(1, 'Test Post', 'test-post');
        $post->setTags($tags);
        $pagination = createPostListPaginatedResult([$post]);

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<a[^>]*href\s*=\s*["\']\/blog\/tag\/php["\']/i')
            ->and($html)->toContain('PHP')
            ->and($html)->toMatch('/<a[^>]*href\s*=\s*["\']\/blog\/tag\/laravel["\']/i')
            ->and($html)->toContain('Laravel');
    });

    it('includes pagination component', function (): void {
        $view = createPostListTestView();

        $posts = [];
        for ($i = 1; $i <= 10; $i++) {
            $posts[] = createPostListPost($i, "Post $i", "post-$i");
        }
        $pagination = createPostListPaginatedResult(
            posts: $posts,
            currentPage: 2,
            totalPages: 5,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, 2, 3, 4, 5],
        );

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\']pagination["\']/i')
            ->and($html)->toMatch('/aria-label\s*=\s*["\']Page navigation["\']/i');
    });

    it('shows message when no posts found', function (): void {
        $view = createPostListTestView();

        $pagination = createPostListEmptyPaginatedResult();

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/no\s+posts/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = createPostListTestView();

        $post = createPostListPost(1, 'Test Post', 'test-post', 'A summary', '2025-03-15 10:00:00');
        $pagination = createPostListPaginatedResult([$post]);

        $html = $view->renderToString('blog::post/index', [
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<main[^>]*>/i')
            ->and($html)->toMatch('/<article[^>]*>/i')
            ->and($html)->toMatch('/<h1[^>]*>/i')
            ->and($html)->toMatch('/<time[^>]*datetime\s*=/i');
    });
});

function createPostListTestView(): LatteView
{
    $blogPackagePath = dirname(__DIR__, 2);
    $tempCacheDir = sys_get_temp_dir() . '/marko-post-list-test-' . uniqid();
    mkdir($tempCacheDir, 0755, true);

    $config = new ConfigRepository([
        'view' => [
            'cache_directory' => $tempCacheDir,
            'extension' => '.latte',
            'auto_refresh' => true,
            'strict_types' => true,
        ],
    ]);

    $moduleRepository = new ModuleRepository([
        new ModuleManifest(
            name: 'marko/blog',
            version: '1.0.0',
            path: $blogPackagePath,
            source: 'vendor',
        ),
    ]);

    $viewConfig = new ViewConfig($config);
    $templateResolver = new ModuleTemplateResolver($moduleRepository, $viewConfig);
    $engineFactory = new LatteEngineFactory($viewConfig);
    $engine = $engineFactory->create();

    return new LatteView($engine, $templateResolver);
}

function createPostListPost(
    int $id,
    string $title,
    string $slug,
    ?string $summary = null,
    ?string $publishedAt = null,
): Post {
    $post = new Post(
        title: $title,
        content: "Content for $title",
        authorId: 1,
        slug: $slug,
        summary: $summary,
    );
    $post->id = $id;
    $post->status = PostStatus::Published;
    $post->publishedAt = $publishedAt ?? '2025-01-15 10:00:00';

    $author = new Author();
    $author->id = 1;
    $author->name = 'John Doe';
    $author->slug = 'john-doe';
    $author->email = 'john.doe@example.com';
    $post->setAuthor($author);

    return $post;
}

function createPostListAuthor(
    int $id,
    string $name,
    string $slug,
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->slug = $slug;
    $author->email = strtolower(str_replace(' ', '.', $name)) . '@example.com';

    return $author;
}

function createPostListCategory(
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

function createPostListTag(
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

function createPostListPaginatedResult(
    array $posts = [],
    int $currentPage = 1,
    int $totalPages = 1,
    bool $hasPreviousPage = false,
    bool $hasNextPage = false,
    array $pageNumbers = [1],
): PaginatedResult {
    return new PaginatedResult(
        items: $posts,
        currentPage: $currentPage,
        totalItems: count($posts) > 0 ? count($posts) * $totalPages : 0,
        perPage: 10,
        totalPages: $totalPages,
        hasPreviousPage: $hasPreviousPage,
        hasNextPage: $hasNextPage,
        pageNumbers: $pageNumbers,
    );
}

function createPostListEmptyPaginatedResult(): PaginatedResult
{
    return new PaginatedResult(
        items: [],
        currentPage: 1,
        totalItems: 0,
        perPage: 10,
        totalPages: 1,
        hasPreviousPage: false,
        hasNextPage: false,
        pageNumbers: [1],
    );
}
