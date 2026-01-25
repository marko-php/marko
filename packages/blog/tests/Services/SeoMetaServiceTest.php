<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Services;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Post;
use Marko\Blog\Services\SeoMetaService;
use Marko\Blog\Services\SeoMetaServiceInterface;

function createSeoMockBlogConfig(
    string $routePrefix = '/blog',
): BlogConfigInterface {
    return new readonly class ($routePrefix) implements BlogConfigInterface
    {
        public function __construct(
            private string $routePrefix,
        ) {}

        public function getPostsPerPage(): int
        {
            return 10;
        }

        public function getCommentMaxDepth(): int
        {
            return 5;
        }

        public function getCommentRateLimitSeconds(): int
        {
            return 30;
        }

        public function getVerificationTokenExpiryDays(): int
        {
            return 7;
        }

        public function getVerificationCookieDays(): int
        {
            return 365;
        }

        public function getRoutePrefix(): string
        {
            return $this->routePrefix;
        }

        public function getVerificationCookieName(): string
        {
            return 'blog_verified';
        }
    };
}

it('generates canonical URL for post page', function (): void {
    $config = createSeoMockBlogConfig(routePrefix: '/blog');
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    $post = new Post(
        title: 'My First Post',
        content: 'Content here',
        authorId: 1,
        slug: 'my-first-post',
    );

    $canonicalUrl = $service->getPostCanonicalUrl($post);

    expect($canonicalUrl)->toBe('https://example.com/blog/my-first-post');
});

it('generates canonical URL for archive pages', function (): void {
    $config = createSeoMockBlogConfig(routePrefix: '/blog');
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    $categoryUrl = $service->getArchiveCanonicalUrl(
        type: 'category',
        slug: 'technology',
    );
    $tagUrl = $service->getArchiveCanonicalUrl(
        type: 'tag',
        slug: 'php',
    );
    $authorUrl = $service->getArchiveCanonicalUrl(
        type: 'author',
        slug: 'john-doe',
    );
    $paginatedUrl = $service->getArchiveCanonicalUrl(
        type: 'category',
        slug: 'technology',
        page: 2,
    );

    expect($categoryUrl)->toBe('https://example.com/blog/category/technology')
        ->and($tagUrl)->toBe('https://example.com/blog/tag/php')
        ->and($authorUrl)->toBe('https://example.com/blog/author/john-doe')
        ->and($paginatedUrl)->toBe('https://example.com/blog/category/technology/page/2');
});

it('generates canonical URL for search results', function (): void {
    $config = createSeoMockBlogConfig(routePrefix: '/blog');
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    $searchUrl = $service->getSearchCanonicalUrl(query: 'php tutorials');
    $paginatedSearchUrl = $service->getSearchCanonicalUrl(query: 'php tutorials', page: 3);

    expect($searchUrl)->toBe('https://example.com/blog/search?q=php+tutorials')
        ->and($paginatedSearchUrl)->toBe('https://example.com/blog/search?q=php+tutorials&page=3');
});

it('generates meta description from post summary', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    $summary = 'This is a great post about PHP programming and best practices.';

    $metaDescription = $service->getMetaDescription($summary);

    expect($metaDescription)->toBe('This is a great post about PHP programming and best practices.');
});

it('generates meta description for archive pages', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    $categoryDescription = $service->getArchiveMetaDescription(
        type: 'category',
        name: 'Technology',
    );
    $tagDescription = $service->getArchiveMetaDescription(
        type: 'tag',
        name: 'PHP',
    );
    $authorDescription = $service->getArchiveMetaDescription(
        type: 'author',
        name: 'John Doe',
    );

    expect($categoryDescription)->toBe('Browse all posts in the Technology category.')
        ->and($tagDescription)->toBe('Browse all posts tagged with PHP.')
        ->and($authorDescription)->toBe('Browse all posts by John Doe.');
});

it('truncates meta description to 160 characters', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    // A very long summary (200+ characters)
    $longSummary = 'This is a very long summary that exceeds the recommended length for meta descriptions. '
        . 'Search engines typically truncate meta descriptions at around 160 characters, '
        . 'so we should ensure our descriptions are properly truncated with an ellipsis.';

    $metaDescription = $service->getMetaDescription($longSummary);

    expect(mb_strlen($metaDescription))->toBeLessThanOrEqual(160)
        ->and($metaDescription)->toEndWith('...');
});

it('generates rel prev link for paginated pages', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    // Page 3 of 5
    $paginatedResult = new PaginatedResult(
        items: [],
        currentPage: 3,
        totalItems: 50,
        perPage: 10,
        totalPages: 5,
        hasPreviousPage: true,
        hasNextPage: true,
        pageNumbers: [1, 2, 3, 4, 5],
    );

    $prevLink = $service->getPrevLink(
        basePath: '/blog/category/technology',
        paginatedResult: $paginatedResult,
    );

    expect($prevLink)->toBe('https://example.com/blog/category/technology/page/2');
});

it('generates rel next link for paginated pages', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    // Page 3 of 5
    $paginatedResult = new PaginatedResult(
        items: [],
        currentPage: 3,
        totalItems: 50,
        perPage: 10,
        totalPages: 5,
        hasPreviousPage: true,
        hasNextPage: true,
        pageNumbers: [1, 2, 3, 4, 5],
    );

    $nextLink = $service->getNextLink(
        basePath: '/blog/category/technology',
        paginatedResult: $paginatedResult,
    );

    expect($nextLink)->toBe('https://example.com/blog/category/technology/page/4');
});

it('omits prev link on first page', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    // Page 1 of 5 (first page)
    $paginatedResult = new PaginatedResult(
        items: [],
        currentPage: 1,
        totalItems: 50,
        perPage: 10,
        totalPages: 5,
        hasPreviousPage: false,
        hasNextPage: true,
        pageNumbers: [1, 2, 3, 4, 5],
    );

    $prevLink = $service->getPrevLink(
        basePath: '/blog/category/technology',
        paginatedResult: $paginatedResult,
    );

    expect($prevLink)->toBeNull();
});

it('omits next link on last page', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    // Page 5 of 5 (last page)
    $paginatedResult = new PaginatedResult(
        items: [],
        currentPage: 5,
        totalItems: 50,
        perPage: 10,
        totalPages: 5,
        hasPreviousPage: true,
        hasNextPage: false,
        pageNumbers: [1, 2, 3, 4, 5],
    );

    $nextLink = $service->getNextLink(
        basePath: '/blog/category/technology',
        paginatedResult: $paginatedResult,
    );

    expect($nextLink)->toBeNull();
});

it('generates page title with site name', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    $pageTitle = $service->getPageTitle('Introduction to PHP');

    expect($pageTitle)->toBe('Introduction to PHP | My Blog');
});

it('implements SeoMetaServiceInterface', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    expect($service)->toBeInstanceOf(SeoMetaServiceInterface::class);
});

it('handles empty summary for meta description', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    expect($service->getMetaDescription(null))->toBe('')
        ->and($service->getMetaDescription(''))->toBe('');
});

it('generates prev link to first page without page number in URL', function (): void {
    $config = createSeoMockBlogConfig();
    $service = new SeoMetaService(
        config: $config,
        baseUrl: 'https://example.com',
        siteName: 'My Blog',
    );

    // Page 2 of 5 - prev link should point to page 1 without /page/1
    $paginatedResult = new PaginatedResult(
        items: [],
        currentPage: 2,
        totalItems: 50,
        perPage: 10,
        totalPages: 5,
        hasPreviousPage: true,
        hasNextPage: true,
        pageNumbers: [1, 2, 3, 4, 5],
    );

    $prevLink = $service->getPrevLink(
        basePath: '/blog/category/technology',
        paginatedResult: $paginatedResult,
    );

    // Should link to base path without /page/1
    expect($prevLink)->toBe('https://example.com/blog/category/technology');
});
