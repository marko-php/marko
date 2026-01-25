<?php

declare(strict_types=1);

use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;

describe('Category Show View', function (): void {
    it('renders category name as page title', function (): void {
        $view = createCategoryShowTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');
        $posts = createCategoryShowEmptyPaginatedResult();

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'path' => [$category],
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/<h1[^>]*>.*Technology.*<\/h1>/s');
    });

    it('displays category hierarchy path as breadcrumbs', function (): void {
        $view = createCategoryShowTestView();

        $parent = createCategoryShowCategory(1, 'Technology', 'technology');
        $child = createCategoryShowCategory(2, 'Programming', 'programming', 1);
        $grandchild = createCategoryShowCategory(3, 'PHP', 'php', 2);
        $posts = createCategoryShowEmptyPaginatedResult();

        $html = $view->renderToString('blog::category/show', [
            'category' => $grandchild,
            'path' => [$parent, $child, $grandchild],
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\'][^"\']*breadcrumbs[^"\']*["\']/i')
            ->and($html)->toContain('Technology')
            ->and($html)->toContain('Programming')
            ->and($html)->toContain('PHP');
    });

    it('renders list of posts in category', function (): void {
        $view = createCategoryShowTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');

        $posts = [
            createCategoryShowPost(1, 'First Post', 'first-post'),
            createCategoryShowPost(2, 'Second Post', 'second-post'),
        ];
        $pagination = createCategoryShowPaginatedResult($posts);

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'path' => [$category],
            'posts' => $pagination,
        ]);

        expect($html)->toContain('First Post')
            ->and($html)->toContain('Second Post');
    });

    it('displays post title summary author and date', function (): void {
        $view = createCategoryShowTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');

        $post = createCategoryShowPost(
            1,
            'Test Post Title',
            'test-post-title',
            'This is the post summary.',
        );
        $pagination = createCategoryShowPaginatedResult([$post]);

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'path' => [$category],
            'posts' => $pagination,
        ]);

        expect($html)->toContain('Test Post Title')
            ->and($html)->toContain('This is the post summary.')
            ->and($html)->toContain('John Doe')
            ->and($html)->toMatch('/January\s+15,?\s+2025|Jan\s+15,?\s+2025|2025-01-15/');
    });

    it('includes pagination component', function (): void {
        $view = createCategoryShowTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');

        $posts = [];
        for ($i = 1; $i <= 10; $i++) {
            $posts[] = createCategoryShowPost($i, "Post $i", "post-$i");
        }
        $pagination = createCategoryShowPaginatedResult(
            posts: $posts,
            currentPage: 1,
            totalItems: 30,
            totalPages: 3,
        );

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'path' => [$category],
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\'][^"\']*pagination[^"\']*["\']/');
    });

    it('shows message when category has no posts', function (): void {
        $view = createCategoryShowTestView();
        $category = createCategoryShowCategory(1, 'Empty Category', 'empty-category');
        $posts = createCategoryShowEmptyPaginatedResult();

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'path' => [$category],
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/no\s+posts/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = createCategoryShowTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');

        $post = createCategoryShowPost(1, 'Test Post', 'test-post');
        $pagination = createCategoryShowPaginatedResult([$post]);

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'path' => [$category],
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\']breadcrumbs["\']/i')
            ->and($html)->toMatch('/aria-label\s*=\s*["\']Category hierarchy["\']/i')
            ->and($html)->toMatch('/<h1[^>]*>/i')
            ->and($html)->toMatch('/<time[^>]*datetime\s*=\s*["\']/i');
    });

    it('includes proper canonical URL', function (): void {
        $view = createCategoryShowTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');
        $posts = createCategoryShowEmptyPaginatedResult();

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'path' => [$category],
            'posts' => $posts,
            'canonicalUrl' => '/blog/category/technology',
        ]);

        expect($html)->toMatch(
            '/<link[^>]*rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']\/blog\/category\/technology["\']/i'
        );
    });
});

function createCategoryShowTestView(): LatteView
{
    $blogPackagePath = dirname(__DIR__, 2);
    $tempCacheDir = sys_get_temp_dir() . '/marko-category-show-test-' . uniqid();
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

function createCategoryShowCategory(
    int $id,
    string $name,
    string $slug,
    ?int $parentId = null,
): Category {
    $category = new Category();
    $category->id = $id;
    $category->name = $name;
    $category->slug = $slug;
    $category->parentId = $parentId;

    return $category;
}

function createCategoryShowEmptyPaginatedResult(): PaginatedResult
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

function createCategoryShowPost(
    int $id,
    string $title,
    string $slug,
    ?string $summary = null,
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
    $post->publishedAt = '2025-01-15 10:00:00';

    $author = new Author();
    $author->id = 1;
    $author->name = 'John Doe';
    $author->slug = 'john-doe';
    $post->setAuthor($author);

    return $post;
}

function createCategoryShowPaginatedResult(
    array $posts,
    int $currentPage = 1,
    int $totalItems = 0,
    int $totalPages = 1,
): PaginatedResult {
    return new PaginatedResult(
        items: $posts,
        currentPage: $currentPage,
        totalItems: $totalItems ?: count($posts),
        perPage: 10,
        totalPages: $totalPages,
        hasPreviousPage: $currentPage > 1,
        hasNextPage: $currentPage < $totalPages,
        pageNumbers: range(1, $totalPages),
    );
}
