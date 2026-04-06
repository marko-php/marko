<?php

declare(strict_types=1);

use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;

describe('Category Show View', function (): void {
    it('renders category name as page title', function (): void {
        $view = createBlogTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');
        $posts = createCategoryShowEmptyPaginatedResult();

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => createCategoryShowBreadcrumbs([$category], $category),
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/<h1[^>]*>.*Technology.*<\/h1>/s');
    });

    it('displays category hierarchy path as breadcrumbs', function (): void {
        $view = createBlogTestView();

        $parent = createCategoryShowCategory(1, 'Technology', 'technology');
        $child = createCategoryShowCategory(2, 'Programming', 'programming', 1);
        $grandchild = createCategoryShowCategory(3, 'PHP', 'php', 2);
        $posts = createCategoryShowEmptyPaginatedResult();

        $html = $view->renderToString('blog::category/show', [
            'category' => $grandchild,
            'breadcrumbs' => createCategoryShowBreadcrumbs([$parent, $child, $grandchild], $grandchild),
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\'][^"\']*breadcrumbs[^"\']*["\']/i')
            ->and($html)->toContain('Technology')
            ->and($html)->toContain('Programming')
            ->and($html)->toContain('PHP');
    });

    it('renders list of posts in category', function (): void {
        $view = createBlogTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');

        $posts = [
            createCategoryShowPost(1, 'First Post', 'first-post'),
            createCategoryShowPost(2, 'Second Post', 'second-post'),
        ];
        $pagination = createCategoryShowPaginatedResult($posts);

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => createCategoryShowBreadcrumbs([$category], $category),
            'posts' => $pagination,
        ]);

        expect($html)->toContain('First Post')
            ->and($html)->toContain('Second Post');
    });

    it('displays post title summary author and date', function (): void {
        $view = createBlogTestView();
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
            'breadcrumbs' => createCategoryShowBreadcrumbs([$category], $category),
            'posts' => $pagination,
        ]);

        expect($html)->toContain('Test Post Title')
            ->and($html)->toContain('This is the post summary.')
            ->and($html)->toContain('John Doe')
            ->and($html)->toMatch('/January\s+15,?\s+2025|Jan\s+15,?\s+2025|2025-01-15/');
    });

    it('includes pagination component', function (): void {
        $view = createBlogTestView();
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
            'breadcrumbs' => createCategoryShowBreadcrumbs([$category], $category),
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\'][^"\']*pagination[^"\']*["\']/');
    });

    it('shows message when category has no posts', function (): void {
        $view = createBlogTestView();
        $category = createCategoryShowCategory(1, 'Empty Category', 'empty-category');
        $posts = createCategoryShowEmptyPaginatedResult();

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => createCategoryShowBreadcrumbs([$category], $category),
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/no\s+posts/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = createBlogTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');

        $post = createCategoryShowPost(1, 'Test Post', 'test-post');
        $pagination = createCategoryShowPaginatedResult([$post]);

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => createCategoryShowBreadcrumbs([$category], $category),
            'posts' => $pagination,
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\']breadcrumbs["\']/i')
            ->and($html)->toMatch('/aria-label\s*=\s*["\']Breadcrumb["\']/i')
            ->and($html)->toMatch('/<h1[^>]*>/i')
            ->and($html)->toMatch('/<time[^>]*datetime\s*=\s*["\']/i');
    });

    it('includes proper canonical URL', function (): void {
        $view = createBlogTestView();
        $category = createCategoryShowCategory(1, 'Technology', 'technology');
        $posts = createCategoryShowEmptyPaginatedResult();

        $html = $view->renderToString('blog::category/show', [
            'category' => $category,
            'breadcrumbs' => createCategoryShowBreadcrumbs([$category], $category),
            'posts' => $posts,
            'canonicalUrl' => '/blog/category/technology',
        ]);

        expect($html)->toMatch(
            '/<link[^>]*rel\s*=\s*["\']canonical["\'][^>]*href\s*=\s*["\']\/blog\/category\/technology["\']/i',
        );
    });
});

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

function createCategoryShowBreadcrumbs(
    array $path,
    Category $current,
): array {
    return array_map(fn ($cat) => [
        'label' => $cat->name,
        'url' => $cat->id === $current->id ? null : "/blog/category/$cat->slug",
    ], $path);
}
