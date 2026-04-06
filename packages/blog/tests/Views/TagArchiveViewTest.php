<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Views\TagArchiveView;

use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;

describe('Tag Archive View', function (): void {
    it('renders tag name as page title', function (): void {
        $view = \createBlogTestView();

        $tag = createTag(1, 'PHP', 'php');
        $posts = createPaginatedResult([]);

        $html = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/<h1[^>]*>.*PHP.*<\/h1>/s');
    });

    it('renders list of posts with tag', function (): void {
        $view = \createBlogTestView();

        $tag = createTag(1, 'PHP', 'php');
        $author = createAuthor(1, 'Jane Doe', 'jane-doe');
        $posts = createPaginatedResult([
            createPost(1, 'First PHP Post', 'first-php-post', 'First summary', '2024-01-15 10:00:00', $author),
            createPost(2, 'Second PHP Post', 'second-php-post', 'Second summary', '2024-01-14 10:00:00', $author),
        ]);

        $html = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
        ]);

        expect($html)->toContain('First PHP Post')
            ->and($html)->toContain('Second PHP Post');
    });

    it('displays post title summary author and date', function (): void {
        $view = \createBlogTestView();

        $tag = createTag(1, 'PHP', 'php');
        $author = createAuthor(1, 'Jane Doe', 'jane-doe');
        $posts = createPaginatedResult([
            createPost(1, 'Test Post Title', 'test-post', 'This is the post summary', '2024-03-15 14:30:00', $author),
        ]);

        $html = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
        ]);

        expect($html)->toContain('Test Post Title')
            ->and($html)->toContain('This is the post summary')
            ->and($html)->toContain('Jane Doe')
            ->and($html)->toMatch('/March\s+15,?\s+2024|2024-03-15|Mar\s+15/i');
    });

    it('includes pagination component', function (): void {
        $view = \createBlogTestView();

        $tag = createTag(1, 'PHP', 'php');
        $author = createAuthor(1, 'Jane Doe', 'jane-doe');
        $posts = createPaginatedResult(
            items: [createPost(1, 'Test Post', 'test-post', null, null, $author)],
            currentPage: 2,
            totalPages: 5,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, 2, 3, 4, 5],
        );

        $html = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\']pagination["\']/i')
            ->and($html)->toMatch('/aria-label\s*=\s*["\']Page navigation["\']/i');
    });

    it('shows message when tag has no posts', function (): void {
        $view = \createBlogTestView();

        $tag = createTag(1, 'PHP', 'php');
        $posts = createPaginatedResult([]);

        $html = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/no\s+posts/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = \createBlogTestView();

        $tag = createTag(1, 'PHP', 'php');
        $author = createAuthor(1, 'Jane Doe', 'jane-doe');
        $posts = createPaginatedResult([
            createPost(1, 'Test Post', 'test-post', 'Summary', '2024-01-15 10:00:00', $author),
        ]);

        $html = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
        ]);

        expect($html)->toMatch('/<main[^>]*>/i')
            ->and($html)->toMatch('/<article[^>]*>/i')
            ->and($html)->toMatch('/<time[^>]*datetime\s*=/i');
    });

    it('includes proper canonical URL', function (): void {
        $view = \createBlogTestView();

        $tag = createTag(1, 'PHP', 'php');
        $posts = createPaginatedResult([]);
        $canonicalUrl = '/blog/tag/php';

        $html = $view->renderToString('blog::tag/index', [
            'tag' => $tag,
            'breadcrumbs' => [['label' => $tag->name]],
            'posts' => $posts,
            'canonicalUrl' => $canonicalUrl,
        ]);

        expect($html)->toMatch('/<link[^>]*rel\s*=\s*["\']canonical["\']/i')
            ->and($html)->toMatch('/href\s*=\s*["\']\/blog\/tag\/php["\']/i');
    });
});

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

function createPost(
    int $id,
    string $title,
    string $slug,
    ?string $summary = null,
    ?string $publishedAt = null,
    ?Author $author = null,
): Post {
    $post = new Post(
        title: $title,
        content: "Content for $title",
        authorId: $author?->id ?? 1,
        slug: $slug,
        summary: $summary,
    );
    $post->id = $id;
    $post->publishedAt = $publishedAt ?? '2024-01-15 10:00:00';

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
    $author->slug = $slug;
    $author->email = strtolower(str_replace(' ', '.', $name)) . '@example.com';

    return $author;
}

function createPaginatedResult(
    array $items = [],
    int $currentPage = 1,
    int $totalPages = 1,
    bool $hasPreviousPage = false,
    bool $hasNextPage = false,
    array $pageNumbers = [1],
): PaginatedResult {
    return new PaginatedResult(
        items: $items,
        currentPage: $currentPage,
        totalItems: count($items) > 0 ? count($items) * $totalPages : 0,
        perPage: 10,
        totalPages: $totalPages,
        hasPreviousPage: $hasPreviousPage,
        hasNextPage: $hasNextPage,
        pageNumbers: $pageNumbers,
    );
}
