<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Services;

use DateTimeImmutable;
use Marko\Blog\Dto\SearchResult;
use Marko\Blog\Entity\AuthorInterface;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Services\SearchService;
use Marko\Blog\Services\SearchServiceInterface;
use RuntimeException;

/**
 * Creates a mock PostInterface for testing.
 */
function createMockPost(
    int $id,
    string $title,
    ?string $summary = null,
    PostStatus $status = PostStatus::Published,
): PostInterface {
    return new class ($id, $title, $summary, $status) implements PostInterface
    {
        public function __construct(
            private int $id,
            private string $title,
            private ?string $summary,
            private PostStatus $status,
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
            return 'Content for ' . $this->title;
        }

        public function getSummary(): ?string
        {
            return $this->summary;
        }

        public function getStatus(): PostStatus
        {
            return $this->status;
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
            return $this->status === PostStatus::Published
                ? new DateTimeImmutable('2024-01-01 12:00:00')
                : null;
        }

        public function getCreatedAt(): ?DateTimeImmutable
        {
            return new DateTimeImmutable('2024-01-01 00:00:00');
        }

        public function getUpdatedAt(): ?DateTimeImmutable
        {
            return new DateTimeImmutable('2024-01-01 00:00:00');
        }

        public function wasUpdatedAfterPublishing(): bool
        {
            return false;
        }

        public function isPublished(): bool
        {
            return $this->status === PostStatus::Published;
        }

        public function isDraft(): bool
        {
            return $this->status === PostStatus::Draft;
        }

        public function isScheduled(): bool
        {
            return $this->status === PostStatus::Scheduled;
        }
    };
}

/**
 * Creates a mock post provider that returns the given posts.
 *
 * @param array<PostInterface> $posts
 * @return callable(): array<PostInterface>
 */
function createPostProvider(
    array $posts,
): callable {
    return fn (): array => $posts;
}

it('finds posts matching search term in title', function (): void {
    $posts = [
        createMockPost(1, 'Introduction to PHP Programming', 'Learn the basics'),
        createMockPost(2, 'Advanced JavaScript Techniques', 'Master JS'),
        createMockPost(3, 'PHP Best Practices', 'Follow these tips'),
    ];

    $service = new SearchService(createPostProvider($posts));

    $results = $service->search('PHP');

    expect($results)->toHaveCount(2)
        ->and($results[0])->toBeInstanceOf(SearchResult::class)
        ->and($results[0]->post->getTitle())->toContain('PHP')
        ->and($results[1]->post->getTitle())->toContain('PHP');
});

it('finds posts matching search term in summary', function (): void {
    $posts = [
        createMockPost(1, 'Getting Started', 'Learn PHP programming from scratch'),
        createMockPost(2, 'Advanced Topics', 'Master JavaScript frameworks'),
        createMockPost(3, 'Web Development', 'Build apps with PHP and Laravel'),
    ];

    $service = new SearchService(createPostProvider($posts));

    $results = $service->search('PHP');

    expect($results)->toHaveCount(2)
        ->and($results[0]->matchedInSummary())->toBeTrue()
        ->and($results[1]->matchedInSummary())->toBeTrue();
});

it('only searches published posts', function (): void {
    $posts = [
        createMockPost(1, 'PHP Guide', 'Published post', PostStatus::Published),
        createMockPost(2, 'PHP Draft', 'Draft post', PostStatus::Draft),
        createMockPost(3, 'PHP Scheduled', 'Scheduled post', PostStatus::Scheduled),
    ];

    $service = new SearchService(createPostProvider($posts));

    $results = $service->search('PHP');

    expect($results)->toHaveCount(1)
        ->and($results[0]->post->getId())->toBe(1)
        ->and($results[0]->post->isPublished())->toBeTrue();
});

it('ranks title matches higher than summary matches', function (): void {
    $posts = [
        createMockPost(1, 'Getting Started', 'Learn PHP programming'),  // summary only
        createMockPost(2, 'PHP Tutorial', 'Learn web development'),     // title only
        createMockPost(3, 'Another Topic', 'Something else'),           // no match
    ];

    $service = new SearchService(createPostProvider($posts));

    $results = $service->search('PHP');

    // Title match (id=2) should rank higher than summary match (id=1)
    expect($results)->toHaveCount(2)
        ->and($results[0]->post->getId())->toBe(2)
        ->and($results[0]->matchedInTitle())->toBeTrue()
        ->and($results[0]->score)->toBeGreaterThan($results[1]->score)
        ->and($results[1]->post->getId())->toBe(1)
        ->and($results[1]->matchedInSummary())->toBeTrue();
});

it('handles multiple search terms', function (): void {
    $posts = [
        createMockPost(1, 'PHP Laravel Tutorial', 'Build web apps'),        // matches both
        createMockPost(2, 'PHP Guide', 'Getting started with PHP'),         // matches PHP only
        createMockPost(3, 'Laravel Framework', 'Modern PHP framework'),     // matches both
        createMockPost(4, 'JavaScript Guide', 'Frontend development'),      // matches neither
    ];

    $service = new SearchService(createPostProvider($posts));

    $results = $service->search('PHP Laravel');

    // Posts matching both terms should rank higher
    expect($results)->toHaveCount(3)
        ->and($results[0]->post->getId())->toBe(1)  // Both in title
        ->and($results[0]->score)->toBeGreaterThan($results[1]->score);
});

it('returns empty results for no matches', function (): void {
    $posts = [
        createMockPost(1, 'PHP Tutorial', 'Learn programming'),
        createMockPost(2, 'Laravel Guide', 'Build web apps'),
    ];

    $service = new SearchService(createPostProvider($posts));

    $results = $service->search('Python');

    expect($results)->toBeEmpty();
});

it('is case insensitive', function (): void {
    $posts = [
        createMockPost(1, 'PHP Tutorial', 'Learn PHP programming'),
        createMockPost(2, 'Laravel Guide', 'Build web apps'),
    ];

    $service = new SearchService(createPostProvider($posts));

    $resultsLower = $service->search('php');
    $resultsUpper = $service->search('PHP');
    $resultsMixed = $service->search('Php');

    expect($resultsLower)->toHaveCount(1)
        ->and($resultsUpper)->toHaveCount(1)
        ->and($resultsMixed)->toHaveCount(1)
        ->and($resultsLower[0]->post->getId())->toBe(1)
        ->and($resultsUpper[0]->post->getId())->toBe(1)
        ->and($resultsMixed[0]->post->getId())->toBe(1);
});

it('escapes special characters in search term', function (): void {
    $posts = [
        createMockPost(1, 'C++ Programming Guide', 'Learn C++ basics'),
        createMockPost(2, 'Regular Expressions (regex)', 'Match patterns with .*'),
        createMockPost(3, 'PHP Tutorial', 'Normal content'),
    ];

    $service = new SearchService(createPostProvider($posts));

    // Should find post with C++ in title (+ is special in regex)
    $resultsCpp = $service->search('C++');

    // Should find post with .* in summary (special regex chars)
    $resultsRegex = $service->search('.*');

    // Should find post with parentheses
    $resultsParens = $service->search('(regex)');

    expect($resultsCpp)->toHaveCount(1)
        ->and($resultsCpp[0]->post->getId())->toBe(1)
        ->and($resultsRegex)->toHaveCount(1)
        ->and($resultsRegex[0]->post->getId())->toBe(2)
        ->and($resultsParens)->toHaveCount(1)
        ->and($resultsParens[0]->post->getId())->toBe(2);
});

it('returns results with relevance score', function (): void {
    $posts = [
        createMockPost(1, 'PHP Tutorial', 'Learn PHP basics'),            // title + summary
        createMockPost(2, 'PHP Guide', 'Web development'),                 // title only
        createMockPost(3, 'Getting Started', 'Introduction to PHP'),       // summary only
    ];

    $service = new SearchService(createPostProvider($posts));

    $results = $service->search('PHP');

    // Title match = 10, Summary match = 3
    // Post 1: 10 + 3 = 13
    // Post 2: 10
    // Post 3: 3
    expect($results)->toHaveCount(3)
        ->and($results[0]->post->getId())->toBe(1)
        ->and($results[0]->score)->toBe(13.0)
        ->and($results[0]->matchedFields)->toBe(['title', 'summary'])
        ->and($results[1]->post->getId())->toBe(2)
        ->and($results[1]->score)->toBe(10.0)
        ->and($results[1]->matchedFields)->toBe(['title'])
        ->and($results[2]->post->getId())->toBe(3)
        ->and($results[2]->score)->toBe(3.0)
        ->and($results[2]->matchedFields)->toBe(['summary']);
});

it('implements SearchServiceInterface', function (): void {
    $service = new SearchService(fn (): array => []);

    expect($service)->toBeInstanceOf(SearchServiceInterface::class);
});

it('supports paginated search results', function (): void {
    $posts = [
        createMockPost(1, 'PHP One', 'Summary'),
        createMockPost(2, 'PHP Two', 'Summary'),
        createMockPost(3, 'PHP Three', 'Summary'),
        createMockPost(4, 'PHP Four', 'Summary'),
        createMockPost(5, 'PHP Five', 'Summary'),
    ];

    $service = new SearchService(createPostProvider($posts));

    // Get first page (2 items)
    $page1 = $service->searchPaginated('PHP', limit: 2, offset: 0);

    // Get second page (2 items)
    $page2 = $service->searchPaginated('PHP', limit: 2, offset: 2);

    // Get third page (1 item remaining)
    $page3 = $service->searchPaginated('PHP', limit: 2, offset: 4);

    expect($page1['total'])->toBe(5)
        ->and($page1['results'])->toHaveCount(2)
        ->and($page2['total'])->toBe(5)
        ->and($page2['results'])->toHaveCount(2)
        ->and($page3['total'])->toBe(5)
        ->and($page3['results'])->toHaveCount(1);
});
