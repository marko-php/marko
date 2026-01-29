<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Views;

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

describe('SEO View Integration', function (): void {
    it('includes canonical link in post page head', function (): void {
        $view = createSeoTestView();

        $post = createSeoTestPost(slug: 'my-amazing-post');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'canonicalUrl' => 'https://example.com/blog/my-amazing-post',
        ]);

        expect($html)->toMatch(
            '/<link[^>]*rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']https:\/\/example\.com\/blog\/my-amazing-post["\']/i',
        );
    });

    it('includes meta description in post page head', function (): void {
        $view = createSeoTestView();

        $post = createSeoTestPost(summary: 'This is a great post about PHP.');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'metaDescription' => 'This is a great post about PHP.',
        ]);

        expect($html)->toMatch(
            '/<meta[^>]*name\s*=\s*["\']description["\'][^>]*content\s*=\s*["\']This is a great post about PHP\.["\']/i',
        );
    });

    it('includes canonical link in archive pages', function (): void {
        $view = createSeoTestView();

        // Test category archive
        $category = createSeoTestCategory(1, 'Technology', 'technology');
        $posts = createSeoTestPaginatedResult();

        $categoryHtml = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => [['label' => $category->name]],
            'posts' => $posts,
            'canonicalUrl' => 'https://example.com/blog/category/technology',
        ]);

        // Test tag archive
        $tag = createSeoTestTag(1, 'PHP', 'php');

        $tagHtml = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
            'canonicalUrl' => 'https://example.com/blog/tag/php',
        ]);

        // Test author archive
        $author = createSeoTestAuthor(slug: 'jane-smith');

        $authorHtml = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => 'https://example.com/blog/author/jane-smith',
        ]);

        expect($categoryHtml)->toMatch(
            '/<link[^>]*rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']https:\/\/example\.com\/blog\/category\/technology["\']/i',
        )
            ->and($tagHtml)->toMatch(
                '/<link[^>]*rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']https:\/\/example\.com\/blog\/tag\/php["\']/i',
            )
            ->and($authorHtml)->toMatch(
                '/<link[^>]*rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']https:\/\/example\.com\/blog\/author\/jane-smith["\']/i',
            );
    });

    it('includes meta description in archive pages', function (): void {
        $view = createSeoTestView();

        // Test category archive
        $category = createSeoTestCategory(1, 'Technology', 'technology');
        $posts = createSeoTestPaginatedResult();

        $categoryHtml = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => [['label' => $category->name]],
            'posts' => $posts,
            'metaDescription' => 'Browse all posts in the Technology category.',
        ]);

        // Test tag archive
        $tag = createSeoTestTag(1, 'PHP', 'php');

        $tagHtml = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
            'metaDescription' => 'Browse all posts tagged with PHP.',
        ]);

        // Test author archive
        $author = createSeoTestAuthor(slug: 'jane-smith');

        $authorHtml = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => 'https://example.com/blog/author/jane-smith',
            'metaDescription' => 'Browse all posts by Jane Smith.',
        ]);

        expect($categoryHtml)->toMatch(
            '/<meta[^>]*name\s*=\s*["\']description["\'][^>]*content\s*=\s*["\']Browse all posts in the Technology category\.["\']/i',
        )
            ->and($tagHtml)->toMatch(
                '/<meta[^>]*name\s*=\s*["\']description["\'][^>]*content\s*=\s*["\']Browse all posts tagged with PHP\.["\']/i',
            )
            ->and($authorHtml)->toMatch(
                '/<meta[^>]*name\s*=\s*["\']description["\'][^>]*content\s*=\s*["\']Browse all posts by Jane Smith\.["\']/i',
            );
    });

    it('includes rel prev and next for paginated pages', function (): void {
        $view = createSeoTestView();

        // Test category archive on page 3 of 5 (has both prev and next)
        $category = createSeoTestCategory(1, 'Technology', 'technology');
        $posts = createSeoTestPaginatedResult(
            currentPage: 3,
            totalPages: 5,
            hasPreviousPage: true,
            hasNextPage: true,
        );

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => [['label' => $category->name]],
            'posts' => $posts,
            'prevLink' => 'https://example.com/blog/category/technology/page/2',
            'nextLink' => 'https://example.com/blog/category/technology/page/4',
        ]);

        expect($html)->toMatch(
            '/<link[^>]*rel\s*=\s*["\']prev["\'][^>]*href\s*=\s*["\']https:\/\/example\.com\/blog\/category\/technology\/page\/2["\']/i',
        )
            ->and($html)->toMatch(
                '/<link[^>]*rel\s*=\s*["\']next["\'][^>]*href\s*=\s*["\']https:\/\/example\.com\/blog\/category\/technology\/page\/4["\']/i',
            );
    });

    it('includes proper page title in title tag', function (): void {
        $view = createSeoTestView();

        // Test post page title
        $post = createSeoTestPost(title: 'Introduction to PHP');

        $postHtml = $view->renderToString('blog::post/show', [
            'post' => $post,
            'pageTitle' => 'Introduction to PHP | My Blog',
        ]);

        // Test category archive page title
        $category = createSeoTestCategory(1, 'Technology', 'technology');
        $posts = createSeoTestPaginatedResult();

        $categoryHtml = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => [['label' => $category->name]],
            'posts' => $posts,
            'pageTitle' => 'Technology | My Blog',
        ]);

        expect($postHtml)->toMatch('/<title[^>]*>.*Introduction to PHP \| My Blog.*<\/title>/is')
            ->and($categoryHtml)->toMatch('/<title[^>]*>.*Technology \| My Blog.*<\/title>/is');
    });

    it('includes og:title meta tag', function (): void {
        $view = createSeoTestView();

        $post = createSeoTestPost(title: 'Introduction to PHP');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'pageTitle' => 'Introduction to PHP | My Blog',
        ]);

        expect($html)->toMatch(
            '/<meta[^>]*property\s*=\s*["\']og:title["\'][^>]*content\s*=\s*["\']Introduction to PHP \| My Blog["\']/i',
        );
    });

    it('includes og:description meta tag', function (): void {
        $view = createSeoTestView();

        $post = createSeoTestPost(summary: 'Learn the basics of PHP programming.');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'metaDescription' => 'Learn the basics of PHP programming.',
        ]);

        expect($html)->toMatch(
            '/<meta[^>]*property\s*=\s*["\']og:description["\'][^>]*content\s*=\s*["\']Learn the basics of PHP programming\.["\']/i',
        );
    });

    it('includes og:url meta tag', function (): void {
        $view = createSeoTestView();

        $post = createSeoTestPost(slug: 'my-amazing-post');

        $html = $view->renderToString('blog::post/show', [
            'post' => $post,
            'canonicalUrl' => 'https://example.com/blog/my-amazing-post',
        ]);

        expect($html)->toMatch(
            '/<meta[^>]*property\s*=\s*["\']og:url["\'][^>]*content\s*=\s*["\']https:\/\/example\.com\/blog\/my-amazing-post["\']/i',
        );
    });

    it('includes og:type meta tag', function (): void {
        $view = createSeoTestView();

        // Post pages should have og:type="article"
        $post = createSeoTestPost();

        $postHtml = $view->renderToString('blog::post/show', [
            'post' => $post,
            'ogType' => 'article',
        ]);

        // Archive pages should have og:type="website"
        $category = createSeoTestCategory(1, 'Technology', 'technology');
        $posts = createSeoTestPaginatedResult();

        $archiveHtml = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => [['label' => $category->name]],
            'posts' => $posts,
            'ogType' => 'website',
        ]);

        expect($postHtml)->toMatch(
            '/<meta[^>]*property\s*=\s*["\']og:type["\'][^>]*content\s*=\s*["\']article["\']/i',
        )
            ->and($archiveHtml)->toMatch(
                '/<meta[^>]*property\s*=\s*["\']og:type["\'][^>]*content\s*=\s*["\']website["\']/i',
            );
    });
});

function createSeoTestView(): LatteView
{
    $blogPackagePath = dirname(__DIR__, 2);
    $tempCacheDir = sys_get_temp_dir() . '/marko-seo-test-' . uniqid();
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

function createSeoTestPost(
    int $id = 1,
    string $title = 'Test Post Title',
    string $slug = 'test-post-title',
    string $content = '<p>This is the post content.</p>',
    ?string $summary = 'A brief summary of the post.',
    string $publishedAt = '2025-01-15 10:00:00',
    ?string $updatedAt = null,
    PostStatus $status = PostStatus::Published,
): Post {
    $post = new Post(
        title: $title,
        content: $content,
        authorId: 1,
        slug: $slug,
        summary: $summary,
    );
    $post->id = $id;
    $post->status = $status;
    $post->publishedAt = $publishedAt;
    $post->updatedAt = $updatedAt;

    $author = createSeoTestAuthor();
    $post->setAuthor($author);

    return $post;
}

function createSeoTestAuthor(
    int $id = 1,
    string $name = 'Jane Smith',
    string $email = 'jane@example.com',
    ?string $bio = 'Jane is a passionate tech writer.',
    string $slug = 'jane-smith',
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->email = $email;
    $author->bio = $bio;
    $author->slug = $slug;

    return $author;
}

function createSeoTestCategory(
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

function createSeoTestTag(
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

function createSeoTestPaginatedResult(
    array $posts = [],
    int $currentPage = 1,
    int $totalItems = 0,
    int $totalPages = 1,
    bool $hasPreviousPage = false,
    bool $hasNextPage = false,
    array $pageNumbers = [1],
): PaginatedResult {
    return new PaginatedResult(
        items: $posts,
        currentPage: $currentPage,
        totalItems: $totalItems ?: count($posts),
        perPage: 10,
        totalPages: $totalPages,
        hasPreviousPage: $hasPreviousPage,
        hasNextPage: $hasNextPage,
        pageNumbers: $pageNumbers,
    );
}
