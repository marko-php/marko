<?php

declare(strict_types=1);

use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteView;
use Marko\View\ModuleTemplateResolver;
use Marko\View\ViewConfig;

describe('Search Results View', function (): void {
    it('includes search bar component with current query', function (): void {
        $view = createSearchResultsTestView();

        $posts = createSearchTestPaginatedResult();

        $html = $view->renderToString('blog::search/index', [
            'query' => 'php tutorials',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toMatch('/<form[^>]*role\s*=\s*["\']search["\']/i')
            ->and($html)->toMatch('/<input[^>]*value\s*=\s*["\']php tutorials["\']/');
    });

    it('displays result count for query', function (): void {
        $view = createSearchResultsTestView();

        $postList = [
            createSearchTestPost(1, 'PHP Basics'),
            createSearchTestPost(2, 'PHP Advanced'),
            createSearchTestPost(3, 'PHP Best Practices'),
        ];
        $posts = createSearchTestPaginatedResult(items: $postList, totalItems: 15);

        $html = $view->renderToString('blog::search/index', [
            'query' => 'php',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toContain('15')
            ->and($html)->toMatch('/result/i');
    });

    it('renders matching posts', function (): void {
        $view = createSearchResultsTestView();

        $postList = [
            createSearchTestPost(1, 'Getting Started with PHP'),
            createSearchTestPost(2, 'Advanced PHP Techniques'),
            createSearchTestPost(3, 'PHP Best Practices'),
        ];
        $posts = createSearchTestPaginatedResult(items: $postList, totalItems: 3);

        $html = $view->renderToString('blog::search/index', [
            'query' => 'php',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toContain('Getting Started with PHP')
            ->and($html)->toContain('Advanced PHP Techniques')
            ->and($html)->toContain('PHP Best Practices');
    });

    it('displays post title summary author and date', function (): void {
        $view = createSearchResultsTestView();

        $author = createSearchTestAuthor(name: 'Jane Smith');
        $postList = [
            createSearchTestPost(
                id: 1,
                title: 'My Amazing Post',
                slug: 'my-amazing-post',
                summary: 'This is an excellent summary of the post.',
                publishedAt: '2024-03-15 14:30:00',
                author: $author,
            ),
        ];
        $posts = createSearchTestPaginatedResult(items: $postList, totalItems: 1);

        $html = $view->renderToString('blog::search/index', [
            'query' => 'amazing',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toContain('My Amazing Post')
            ->and($html)->toContain('This is an excellent summary of the post.')
            ->and($html)->toContain('Jane Smith')
            ->and($html)->toContain('2024-03-15');
    });

    it('includes pagination component', function (): void {
        $view = createSearchResultsTestView();

        $postList = [
            createSearchTestPost(1, 'Post 1'),
            createSearchTestPost(2, 'Post 2'),
        ];
        $posts = createSearchTestPaginatedResult(
            items: $postList,
            currentPage: 2,
            totalItems: 25,
            perPage: 10,
            totalPages: 3,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, 2, 3],
        );

        $html = $view->renderToString('blog::search/index', [
            'query' => 'test',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\']pagination["\']/i')
            ->and($html)->toMatch('/<a[^>]*class\s*=\s*["\'][^"\']*pagination-prev[^"\']*["\']/');
    });

    it('preserves search query in pagination links', function (): void {
        $view = createSearchResultsTestView();

        $postList = [
            createSearchTestPost(1, 'Post 1'),
            createSearchTestPost(2, 'Post 2'),
        ];
        $posts = createSearchTestPaginatedResult(
            items: $postList,
            currentPage: 2,
            totalItems: 25,
            perPage: 10,
            totalPages: 3,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, 2, 3],
        );

        $html = $view->renderToString('blog::search/index', [
            'query' => 'php framework',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toMatch('/href\s*=\s*["\'][^"\']*q=php\+framework[^"\']*["\']/i');
    });

    it('shows no results message when empty', function (): void {
        $view = createSearchResultsTestView();

        $posts = createSearchTestPaginatedResult(items: [], totalItems: 0);

        $html = $view->renderToString('blog::search/index', [
            'query' => 'nonexistent query',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toMatch('/no\s+(posts|results)/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = createSearchResultsTestView();

        $postList = [createSearchTestPost(1, 'Post 1')];
        $posts = createSearchTestPaginatedResult(items: $postList, totalItems: 1);

        $html = $view->renderToString('blog::search/index', [
            'query' => 'test',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toMatch('/<header[^>]*class\s*=\s*["\'][^"\']*search-header[^"\']*["\']/i')
            ->and($html)->toMatch('/<section[^>]*class\s*=\s*["\'][^"\']*search-results[^"\']*["\']/i');
    });

    it('includes search input with label', function (): void {
        $view = createSearchResultsTestView();

        $posts = createSearchTestPaginatedResult();

        $html = $view->renderToString('blog::search/index', [
            'query' => '',
            'posts' => $posts,
            'canonicalUrl' => '/blog/search',
        ]);

        expect($html)->toMatch('/<label[^>]*for\s*=\s*["\']search-input["\']/i')
            ->and($html)->toMatch('/<input[^>]*id\s*=\s*["\']search-input["\']/i');
    });
});

function createSearchResultsTestView(): LatteView
{
    $blogPackagePath = dirname(__DIR__, 2);
    $tempCacheDir = sys_get_temp_dir() . '/marko-search-results-test-' . uniqid();
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

function createSearchTestAuthor(
    int $id = 1,
    string $name = 'John Doe',
    string $email = 'john@example.com',
    ?string $bio = 'A passionate writer and developer.',
    string $slug = 'john-doe',
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->email = $email;
    $author->bio = $bio;
    $author->slug = $slug;

    return $author;
}

function createSearchTestPost(
    int $id,
    string $title,
    string $slug = '',
    ?string $summary = null,
    string $publishedAt = '2024-01-15 10:00:00',
    PostStatus $status = PostStatus::Published,
    ?Author $author = null,
): Post {
    $post = new Post();
    $post->id = $id;
    $post->title = $title;
    $post->slug = $slug ?: strtolower(str_replace(' ', '-', $title));
    $post->summary = $summary ?? "Summary for $title";
    $post->publishedAt = $publishedAt;
    $post->status = $status;
    $post->setAuthor($author ?? createSearchTestAuthor());

    return $post;
}

function createSearchTestPaginatedResult(
    array $items = [],
    int $currentPage = 1,
    int $totalItems = 0,
    int $perPage = 10,
    int $totalPages = 0,
    bool $hasPreviousPage = false,
    bool $hasNextPage = false,
    array $pageNumbers = [],
): PaginatedResult {
    $totalItems = $totalItems ?: count($items);
    $totalPages = $totalPages ?: (int) ceil($totalItems / $perPage) ?: 0;

    return new PaginatedResult(
        items: $items,
        currentPage: $currentPage,
        totalItems: $totalItems,
        perPage: $perPage,
        totalPages: $totalPages,
        hasPreviousPage: $hasPreviousPage,
        hasNextPage: $hasNextPage,
        pageNumbers: $pageNumbers ?: ($totalPages > 0 ? range(1, $totalPages) : []),
    );
}
