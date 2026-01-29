<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Controllers\SearchController;

use DateTimeImmutable;
use Marko\Blog\Controllers\SearchController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Dto\SearchResult;
use Marko\Blog\Entity\AuthorInterface;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SearchServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;
use ReflectionClass;
use RuntimeException;

\it('returns search results at GET /blog/search', function (): void {
    $reflection = new ReflectionClass(SearchController::class);
    $method = $reflection->getMethod('index');
    $attributes = $method->getAttributes(Get::class);

    \expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    \expect($routeAttribute->path)->toBe('/blog/search');
});

\it('requires q query parameter', function (): void {
    // Verify the index method has a q parameter
    $reflection = new ReflectionClass(SearchController::class);
    $method = $reflection->getMethod('index');
    $parameters = $method->getParameters();

    // Find the q parameter
    $qParam = null;
    foreach ($parameters as $param) {
        if ($param->getName() === 'q') {
            $qParam = $param;
            break;
        }
    }

    \expect($qParam)->not->toBeNull()
        ->and($qParam->getType()->getName())->toBe('string');
});

\it('returns empty results when q is empty', function (): void {
    $searchService = createSearchService(results: []);
    $paginationService = createPaginationService(createPaginatedResult());
    $view = createView();

    $controller = new SearchController($searchService, $paginationService, $view);
    $response = $controller->index(q: '');

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200);
});

\it('returns posts matching search term', function (): void {
    // Create mock posts to search for
    $mockPost = createMockPost(1, 'PHP Tutorial');
    $searchResult = new SearchResult($mockPost, 10.0, ['title']);
    $paginatedResult = createPaginatedResult([$searchResult], 1, 1);

    $searchService = createSearchService(results: [$searchResult], total: 1);
    $paginationService = createPaginationService($paginatedResult);

    $capturedData = [];
    $view = createViewWithCapture($capturedData);

    $controller = new SearchController($searchService, $paginationService, $view);
    $response = $controller->index(q: 'PHP');

    \expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($capturedData)->toHaveKey('results')
        ->and($capturedData['results']->items)->toHaveCount(1)
        ->and($capturedData['results']->items[0])->toBeInstanceOf(SearchResult::class);
});

\it('orders results by relevance score', function (): void {
    // Create results already sorted by relevance (this is what SearchService returns)
    $highScore = new SearchResult(createMockPost(1, 'PHP Tutorial'), 13.0, ['title', 'summary']);
    $medScore = new SearchResult(createMockPost(2, 'PHP Guide'), 10.0, ['title']);
    $lowScore = new SearchResult(createMockPost(3, 'Getting Started'), 3.0, ['summary']);

    // Results ordered by score descending (as SearchService provides them)
    $orderedResults = [$highScore, $medScore, $lowScore];
    $paginatedResult = createPaginatedResult($orderedResults, 1, 3);

    $searchService = createSearchService(results: $orderedResults, total: 3);
    $paginationService = createPaginationService($paginatedResult);

    $capturedData = [];
    $view = createViewWithCapture($capturedData);

    $controller = new SearchController($searchService, $paginationService, $view);
    $controller->index(q: 'PHP');

    // Results should maintain the relevance order from SearchService
    \expect($capturedData['results']->items)->toHaveCount(3)
        ->and($capturedData['results']->items[0]->score)->toBe(13.0)
        ->and($capturedData['results']->items[1]->score)->toBe(10.0)
        ->and($capturedData['results']->items[2]->score)->toBe(3.0);
});

\it('accepts page query parameter for pagination', function (): void {
    // Verify the index method has a page parameter with default value of 1
    $reflection = new ReflectionClass(SearchController::class);
    $method = $reflection->getMethod('index');
    $parameters = $method->getParameters();

    // Find the page parameter
    $pageParam = null;
    foreach ($parameters as $param) {
        if ($param->getName() === 'page') {
            $pageParam = $param;
            break;
        }
    }

    \expect($pageParam)->not->toBeNull()
        ->and($pageParam->getType()->getName())->toBe('int')
        ->and($pageParam->getDefaultValue())->toBe(1);

    // Test that page 2 works correctly
    $paginatedResult = createPaginatedResult([], 2, 25);
    $searchService = createSearchService(results: [], total: 25);
    $paginationService = createPaginationService($paginatedResult);
    $view = createView();

    $controller = new SearchController($searchService, $paginationService, $view);
    $response = $controller->index(q: 'PHP', page: 2);

    \expect($response->statusCode())->toBe(200);
});

\it('includes pagination metadata in response', function (): void {
    $results = [
        new SearchResult(createMockPost(1, 'Post 1'), 10.0, ['title']),
        new SearchResult(createMockPost(2, 'Post 2'), 8.0, ['title']),
    ];

    $paginatedResult = createPaginatedResult(
        items: $results,
        currentPage: 2,
        totalItems: 25,
        perPage: 10,
    );

    $searchService = createSearchService(results: $results, total: 25);
    $paginationService = createPaginationService($paginatedResult);

    $capturedData = [];
    $view = createViewWithCapture($capturedData);

    $controller = new SearchController($searchService, $paginationService, $view);
    $controller->index(q: 'PHP', page: 2);

    // Verify pagination metadata is passed to the view
    \expect($capturedData)->toHaveKey('results')
        ->and($capturedData['results'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['results']->currentPage)->toBe(2)
        ->and($capturedData['results']->totalItems)->toBe(25)
        ->and($capturedData['results']->perPage)->toBe(10)
        ->and($capturedData['results']->totalPages)->toBe(3)
        ->and($capturedData['results']->hasPreviousPage)->toBeTrue()
        ->and($capturedData['results']->hasNextPage)->toBeTrue();
});

\it('includes search term in response for display', function (): void {
    $paginatedResult = createPaginatedResult([], 1, 0);
    $searchService = createSearchService(results: []);
    $paginationService = createPaginationService($paginatedResult);

    $capturedData = [];
    $view = createViewWithCapture($capturedData);

    $controller = new SearchController($searchService, $paginationService, $view);
    $controller->index(q: 'PHP Laravel');

    // Verify search term is passed to the view for display
    \expect($capturedData)->toHaveKey('query')
        ->and($capturedData['query'])->toBe('PHP Laravel');
});

\it('renders using view template', function (): void {
    $paginatedResult = createPaginatedResult([], 1, 0);
    $searchService = createSearchService(results: []);
    $paginationService = createPaginationService($paginatedResult);

    $capturedTemplate = null;
    $view = createViewWithTemplateCapture($capturedTemplate);

    $controller = new SearchController($searchService, $paginationService, $view);
    $controller->index(q: 'PHP');

    // Verify the correct template is used
    \expect($capturedTemplate)->toBe('blog::search/index');
});

// Helper function to create mock ViewInterface that captures template name
function createViewWithTemplateCapture(
    ?string &$capturedTemplate,
): ViewInterface {
    return new class ($capturedTemplate) implements ViewInterface
    {
        public function __construct(
            private ?string &$capturedTemplate,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            $this->capturedTemplate = $template;

            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            return "rendered: $template";
        }
    };
}

// Helper function to create mock SearchServiceInterface
function createSearchService(
    array $results = [],
    int $total = 0,
): SearchServiceInterface {
    return new readonly class ($results, $total) implements SearchServiceInterface
    {
        public function __construct(
            private array $results,
            private int $total,
        ) {}

        public function search(
            string $query,
        ): array {
            return $this->results;
        }

        public function searchPaginated(
            string $query,
            int $limit,
            int $offset,
        ): array {
            return [
                'results' => $this->results,
                'total' => $this->total ?: count($this->results),
            ];
        }
    };
}

// Helper function to create mock PaginationServiceInterface
function createPaginationService(
    PaginatedResult $paginateResult,
): PaginationServiceInterface {
    return new readonly class ($paginateResult) implements PaginationServiceInterface
    {
        public function __construct(
            private PaginatedResult $paginateResult,
        ) {}

        public function paginate(
            array $items,
            int $totalItems,
            int $currentPage,
            ?int $perPage = null,
        ): PaginatedResult {
            return $this->paginateResult;
        }

        public function calculateOffset(
            int $page,
            ?int $perPage = null,
        ): int {
            $perPage = $perPage ?? 10;

            return ($page - 1) * $perPage;
        }

        public function getPerPage(): int
        {
            return 10;
        }
    };
}

// Helper function to create mock PaginatedResult
function createPaginatedResult(
    array $items = [],
    int $currentPage = 1,
    int $totalItems = 0,
    int $perPage = 10,
): PaginatedResult {
    $totalItems = $totalItems ?: count($items);
    $totalPages = (int) ceil($totalItems / $perPage) ?: 0;

    return new PaginatedResult(
        items: $items,
        currentPage: $currentPage,
        totalItems: $totalItems,
        perPage: $perPage,
        totalPages: $totalPages,
        hasPreviousPage: $currentPage > 1,
        hasNextPage: $currentPage < $totalPages,
        pageNumbers: range(1, max(1, $totalPages)),
    );
}

// Helper function to create mock ViewInterface
function createView(): ViewInterface
{
    return new class () implements ViewInterface
    {
        public function render(
            string $template,
            array $data = [],
        ): Response {
            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            return "rendered: $template";
        }
    };
}

// Helper function to create mock ViewInterface that captures data
function createViewWithCapture(
    array &$capturedData,
): ViewInterface {
    return new class ($capturedData) implements ViewInterface
    {
        public function __construct(
            private array &$capturedData,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            foreach ($data as $key => $value) {
                $this->capturedData[$key] = $value;
            }

            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            return "rendered: $template";
        }
    };
}

// Helper function to create mock PostInterface
function createMockPost(
    int $id,
    string $title,
): PostInterface {
    return new readonly class ($id, $title) implements PostInterface
    {
        public function __construct(
            private int $id,
            private string $title,
        ) {}

        public function getId(): ?int
        {
            return $this->id;
        }

        public function getTitle(): string
        {
            return $this->title;
        }

        public function getSlug(): string
        {
            return strtolower(str_replace(' ', '-', $this->title));
        }

        public function getContent(): string
        {
            return 'Content';
        }

        public function getSummary(): ?string
        {
            return 'Summary';
        }

        public function getStatus(): PostStatus
        {
            return PostStatus::Published;
        }

        public function getAuthorId(): int
        {
            return 1;
        }

        public function getAuthor(): AuthorInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function getScheduledAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function getPublishedAt(): ?DateTimeImmutable
        {
            return new DateTimeImmutable();
        }

        public function getCreatedAt(): ?DateTimeImmutable
        {
            return new DateTimeImmutable();
        }

        public function getUpdatedAt(): ?DateTimeImmutable
        {
            return new DateTimeImmutable();
        }

        public function wasUpdatedAfterPublishing(): bool
        {
            return false;
        }

        public function isPublished(): bool
        {
            return true;
        }

        public function isDraft(): bool
        {
            return false;
        }

        public function isScheduled(): bool
        {
            return false;
        }
    };
}
