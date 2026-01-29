<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Services;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Services\PaginationService;
use Marko\Blog\Services\PaginationServiceInterface;

function createMockBlogConfig(
    int $postsPerPage = 10,
): BlogConfigInterface {
    return new readonly class ($postsPerPage) implements BlogConfigInterface
    {
        public function __construct(
            private int $postsPerPage,
        ) {}

        public function getPostsPerPage(): int
        {
            return $this->postsPerPage;
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
            return '/blog';
        }

        public function getVerificationCookieName(): string
        {
            return 'blog_verified';
        }

        public function getSiteName(): string
        {
            return 'Test Blog';
        }
    };
}

it('calculates total pages from total items and per page', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));

    $result = $service->paginate(items: [], totalItems: 25, currentPage: 1);

    expect($result)->toBeInstanceOf(PaginatedResult::class)
        ->and($result->totalPages)->toBe(3);
});

it('determines if has previous page', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));

    $resultPage1 = $service->paginate(items: [], totalItems: 30, currentPage: 1);
    $resultPage2 = $service->paginate(items: [], totalItems: 30, currentPage: 2);
    $resultPage3 = $service->paginate(items: [], totalItems: 30, currentPage: 3);

    expect($resultPage1->hasPreviousPage)->toBeFalse()
        ->and($resultPage2->hasPreviousPage)->toBeTrue()
        ->and($resultPage3->hasPreviousPage)->toBeTrue();
});

it('determines if has next page', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));

    $resultPage1 = $service->paginate(items: [], totalItems: 30, currentPage: 1);
    $resultPage2 = $service->paginate(items: [], totalItems: 30, currentPage: 2);
    $resultPage3 = $service->paginate(items: [], totalItems: 30, currentPage: 3);

    expect($resultPage1->hasNextPage)->toBeTrue()
        ->and($resultPage2->hasNextPage)->toBeTrue()
        ->and($resultPage3->hasNextPage)->toBeFalse();
});

it('returns current page number', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));

    $result = $service->paginate(items: [], totalItems: 50, currentPage: 3);

    expect($result->currentPage)->toBe(3);
});

it('calculates offset for database query', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));

    expect($service->calculateOffset(page: 1))->toBe(0)
        ->and($service->calculateOffset(page: 2))->toBe(10)
        ->and($service->calculateOffset(page: 3))->toBe(20)
        ->and($service->calculateOffset(page: 1, perPage: 25))->toBe(0)
        ->and($service->calculateOffset(page: 2, perPage: 25))->toBe(25)
        ->and($service->calculateOffset(page: 3, perPage: 25))->toBe(50);
});

it('generates array of page numbers for display', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));

    $result = $service->paginate(items: [], totalItems: 50, currentPage: 1);

    expect($result->pageNumbers)->toBe([1, 2, 3, 4, 5]);
});

it('limits displayed page numbers with ellipsis logic', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));

    // Page 5 of 20: [1, null, 4, 5, 6, null, 20]
    $resultMiddle = $service->paginate(items: [], totalItems: 200, currentPage: 5);
    expect($resultMiddle->pageNumbers)->toBe([1, null, 4, 5, 6, null, 20]);

    // Page 1 of 5: [1, 2, 3, 4, 5] - no ellipsis needed
    $resultSmall = $service->paginate(items: [], totalItems: 50, currentPage: 1);
    expect($resultSmall->pageNumbers)->toBe([1, 2, 3, 4, 5]);

    // Page 2 of 20: [1, 2, 3, null, 20]
    $resultNearStart = $service->paginate(items: [], totalItems: 200, currentPage: 2);
    expect($resultNearStart->pageNumbers)->toBe([1, 2, 3, null, 20]);

    // Page 19 of 20: [1, null, 18, 19, 20]
    $resultNearEnd = $service->paginate(items: [], totalItems: 200, currentPage: 19);
    expect($resultNearEnd->pageNumbers)->toBe([1, null, 18, 19, 20]);
});

it('uses configured posts_per_page from BlogConfig', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 15));

    expect($service->getPerPage())->toBe(15);

    $result = $service->paginate(items: [], totalItems: 45, currentPage: 1);

    expect($result->perPage)->toBe(15)
        ->and($result->totalPages)->toBe(3);
});

it('creates PaginatedResult with items and metadata', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));
    $items = ['post1', 'post2', 'post3'];

    $result = $service->paginate(items: $items, totalItems: 25, currentPage: 2);

    expect($result->items)->toBe($items)
        ->and($result->currentPage)->toBe(2)
        ->and($result->totalItems)->toBe(25)
        ->and($result->perPage)->toBe(10)
        ->and($result->totalPages)->toBe(3)
        ->and($result->hasPreviousPage)->toBeTrue()
        ->and($result->hasNextPage)->toBeTrue()
        ->and($result->pageNumbers)->toBeArray();
});

it('handles edge case of zero total items', function (): void {
    $service = new PaginationService(createMockBlogConfig(postsPerPage: 10));

    $result = $service->paginate(items: [], totalItems: 0, currentPage: 1);

    expect($result->totalPages)->toBe(0)
        ->and($result->hasPreviousPage)->toBeFalse()
        ->and($result->hasNextPage)->toBeFalse()
        ->and($result->isEmpty())->toBeTrue()
        ->and($result->shouldShowPagination())->toBeFalse()
        ->and($result->pageNumbers)->toBe([]);
});

it('implements PaginationServiceInterface', function (): void {
    $service = new PaginationService(createMockBlogConfig());

    expect($service)->toBeInstanceOf(PaginationServiceInterface::class);
});
