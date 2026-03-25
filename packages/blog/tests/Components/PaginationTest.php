<?php

declare(strict_types=1);

use Marko\Blog\Dto\PaginatedResult;
describe('Pagination Component', function (): void {
    it('renders previous link when not on first page', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 3,
            totalPages: 10,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, null, 2, 3, 4, null, 10],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->toMatch('/<a[^>]*class\s*=\s*["\'][^"\']*pagination-prev[^"\']*["\']/');
    });

    it('hides previous link on first page', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 1,
            totalPages: 10,
            hasPreviousPage: false,
            hasNextPage: true,
            pageNumbers: [1, 2, 3, null, 10],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->not->toMatch('/<a[^>]*class\s*=\s*["\'][^"\']*pagination-prev[^"\']*["\']/');
    });

    it('renders next link when not on last page', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 3,
            totalPages: 10,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, null, 2, 3, 4, null, 10],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->toMatch('/<a[^>]*class\s*=\s*["\'][^"\']*pagination-next[^"\']*["\']/');
    });

    it('hides next link on last page', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 10,
            totalPages: 10,
            hasPreviousPage: true,
            hasNextPage: false,
            pageNumbers: [1, null, 9, 10],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->not->toMatch('/<a[^>]*class\s*=\s*["\'][^"\']*pagination-next[^"\']*["\']/');
    });

    it('renders numbered page links', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 1,
            totalPages: 5,
            hasPreviousPage: false,
            hasNextPage: true,
            pageNumbers: [1, 2, 3, 4, 5],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->toContain('>1<')
            ->and($html)->toContain('>2<')
            ->and($html)->toContain('>3<')
            ->and($html)->toContain('>4<')
            ->and($html)->toContain('>5<');
    });

    it('highlights current page', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 3,
            totalPages: 5,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, 2, 3, 4, 5],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->toMatch(
            '/<a[^>]*class\s*=\s*["\'][^"\']*pagination-current[^"\']*["\'][^>]*aria-current\s*=\s*["\']page["\']/',
        );
    });

    it('shows ellipsis for large page ranges', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 5,
            totalPages: 20,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, null, 4, 5, 6, null, 20],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->toMatch('/<li[^>]*class\s*=\s*["\']pagination-ellipsis["\']/');
    });

    it('includes proper href with page parameter', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 1,
            totalPages: 5,
            hasPreviousPage: false,
            hasNextPage: true,
            pageNumbers: [1, 2, 3, 4, 5],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->toMatch('/href\s*=\s*["\']\/blog\?page=2["\']/');
    });

    it('preserves existing query parameters in links', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 1,
            totalPages: 5,
            hasPreviousPage: false,
            hasNextPage: true,
            pageNumbers: [1, 2, 3, 4, 5],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
            'queryParams' => ['q' => 'search term'],
        ]);

        expect($html)->toMatch('/href\s*=\s*["\']\/blog\?q=search\+term&amp;page=2["\']/');
    });

    it('renders nothing when only one page', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 1,
            totalPages: 1,
            hasPreviousPage: false,
            hasNextPage: false,
            pageNumbers: [1],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        $trimmedHtml = trim($html);
        expect($trimmedHtml)->toBe('');
    });

    it('has semantic HTML with nav and aria labels', function (): void {
        $view = createBlogTestView();

        $pagination = createPaginatedResult(
            currentPage: 2,
            totalPages: 5,
            hasPreviousPage: true,
            hasNextPage: true,
            pageNumbers: [1, 2, 3, 4, 5],
        );

        $html = $view->renderToString('blog::pagination/index', [
            'pagination' => $pagination,
            'baseUrl' => '/blog',
        ]);

        expect($html)->toMatch('/<nav[^>]*class\s*=\s*["\']pagination["\']/i')
            ->and($html)->toMatch('/aria-label\s*=\s*["\']Page navigation["\']/i');
    });
});

function createPaginatedResult(
    int $currentPage,
    int $totalPages,
    bool $hasPreviousPage,
    bool $hasNextPage,
    array $pageNumbers,
): PaginatedResult {
    return new PaginatedResult(
        items: [],
        currentPage: $currentPage,
        totalItems: $totalPages * 10,
        perPage: 10,
        totalPages: $totalPages,
        hasPreviousPage: $hasPreviousPage,
        hasNextPage: $hasNextPage,
        pageNumbers: $pageNumbers,
    );
}
