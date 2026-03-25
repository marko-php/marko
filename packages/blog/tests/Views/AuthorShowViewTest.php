<?php

declare(strict_types=1);

use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
describe('Author Show View', function (): void {
    it('renders author name as page title', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor(name: 'Jane Smith');
        $posts = createTestPaginatedResult();

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/jane-smith',
        ]);

        expect($html)->toMatch('/<h1[^>]*>.*Jane Smith.*<\/h1>/s');
    });

    it('displays author bio', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor(bio: 'A passionate PHP developer who loves clean code.');
        $posts = createTestPaginatedResult();

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/john-doe',
        ]);

        expect($html)->toContain('A passionate PHP developer who loves clean code.');
    });

    it('displays author email', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor(email: 'jane.smith@example.com');
        $posts = createTestPaginatedResult();

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/john-doe',
        ]);

        expect($html)->toContain('jane.smith@example.com');
    });

    it('renders list of posts by author', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor();
        $postList = [
            createTestPost(1, 'First Post', 'first-post'),
            createTestPost(2, 'Second Post', 'second-post'),
            createTestPost(3, 'Third Post', 'third-post'),
        ];
        $posts = createTestPaginatedResult(items: $postList, totalItems: 3);

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/john-doe',
        ]);

        expect($html)->toContain('First Post')
            ->and($html)->toContain('Second Post')
            ->and($html)->toContain('Third Post');
    });

    it('displays post title summary and date', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor();
        $postList = [
            createTestPost(
                id: 1,
                title: 'My Amazing Post',
                slug: 'my-amazing-post',
                summary: 'This is an excellent summary of the post.',
                publishedAt: '2024-03-15 14:30:00',
            ),
        ];
        $posts = createTestPaginatedResult(items: $postList, totalItems: 1);

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/john-doe',
        ]);

        expect($html)->toContain('My Amazing Post')
            ->and($html)->toContain('This is an excellent summary of the post.')
            ->and($html)->toContain('2024-03-15');
    });

    it('includes pagination component', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor();
        $postList = [
            createTestPost(1, 'Post 1'),
            createTestPost(2, 'Post 2'),
        ];
        $posts = createTestPaginatedResult(
            items: $postList,
            currentPage: 2,
            totalItems: 25,
            perPage: 10,
            totalPages: 3,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, 2, 3],
        );

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/john-doe',
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\']pagination["\']/i')
            ->and($html)->toMatch('/<a[^>]*class\s*=\s*["\'][^"\']*pagination-prev[^"\']*["\']/');
    });

    it('shows message when author has no posts', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor();
        $posts = createTestPaginatedResult(items: [], totalItems: 0);

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/john-doe',
        ]);

        expect($html)->toMatch('/no\s+posts/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor();
        $postList = [createTestPost(1, 'Post 1')];
        $posts = createTestPaginatedResult(items: $postList, totalItems: 1);

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/john-doe',
        ]);

        expect($html)->toMatch('/<header[^>]*class\s*=\s*["\'][^"\']*author-header[^"\']*["\']/i')
            ->and($html)->toMatch('/<section[^>]*class\s*=\s*["\'][^"\']*author-posts[^"\']*["\']/i');
    });

    it('includes proper canonical URL', function (): void {
        $view = createBlogTestView();

        $author = createTestAuthor(slug: 'jane-smith');
        $posts = createTestPaginatedResult(items: [], totalItems: 0);

        $html = $view->renderToString('blog::author/show', [
            'author' => $author,
            'breadcrumbs' => [['label' => $author->getName()]],
            'posts' => $posts,
            'canonicalUrl' => '/blog/author/jane-smith',
        ]);

        expect($html)->toMatch('/<link[^>]*rel\s*=\s*["\']canonical["\']/i')
            ->and($html)->toContain('/blog/author/jane-smith');
    });
});

function createTestAuthor(
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

function createTestPost(
    int $id,
    string $title,
    string $slug = '',
    ?string $summary = null,
    string $publishedAt = '2024-01-15 10:00:00',
    PostStatus $status = PostStatus::Published,
): Post {
    $post = new Post();
    $post->id = $id;
    $post->title = $title;
    $post->slug = $slug ?: strtolower(str_replace(' ', '-', $title));
    $post->summary = $summary ?? "Summary for $title";
    $post->publishedAt = $publishedAt;
    $post->status = $status;

    return $post;
}

function createTestPaginatedResult(
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
