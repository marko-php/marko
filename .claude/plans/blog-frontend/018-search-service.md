# Task 018: Search Service

**Status**: pending
**Depends on**: 007
**Retry count**: 0

## Description
Create a search service that finds posts by searching title and summary fields. Results are sorted by relevance (title matches weighted higher than summary matches).

## Context
- Related files: `packages/blog/src/Services/SearchService.php`, `packages/blog/src/Dto/SearchResult.php`
- Patterns to follow: Interface/implementation split
- Simple LIKE-based search initially, interface allows swapping to full-text later

## Requirements (Test Descriptions)
- [ ] `it finds posts matching search term in title`
- [ ] `it finds posts matching search term in summary`
- [ ] `it only searches published posts`
- [ ] `it ranks title matches higher than summary matches`
- [ ] `it handles multiple search terms`
- [ ] `it returns empty results for no matches`
- [ ] `it is case insensitive`
- [ ] `it escapes special characters in search term`
- [ ] `it returns results with relevance score`

## SearchResult DTO

```php
/**
 * Immutable DTO representing a single search result with relevance score.
 */
readonly class SearchResult
{
    /**
     * @param PostInterface $post The matching post
     * @param float $score Relevance score (higher = more relevant)
     * @param array $matchedFields Fields where the term was found ['title', 'summary']
     */
    public function __construct(
        public PostInterface $post,
        public float $score,
        public array $matchedFields,
    ) {}

    /**
     * Check if the search term matched in the title.
     */
    public function matchedInTitle(): bool
    {
        return in_array('title', $this->matchedFields, true);
    }

    /**
     * Check if the search term matched in the summary.
     */
    public function matchedInSummary(): bool
    {
        return in_array('summary', $this->matchedFields, true);
    }
}
```

## SearchServiceInterface

```php
interface SearchServiceInterface
{
    /**
     * Search for posts matching the given query.
     *
     * @param string $query The search query (may contain multiple terms)
     * @return SearchResult[] Array of search results sorted by relevance (highest first)
     */
    public function search(string $query): array;

    /**
     * Search with pagination support.
     *
     * @param string $query The search query
     * @param int $limit Maximum results to return
     * @param int $offset Number of results to skip
     * @return array{results: SearchResult[], total: int}
     */
    public function searchPaginated(
        string $query,
        int $limit,
        int $offset,
    ): array;
}
```

## Relevance Scoring

The default implementation uses simple scoring:

```php
// Title match: 10 points per term
// Summary match: 3 points per term
// Exact phrase match bonus: +5 points

// Example: searching "php tutorial"
// Post A: title="PHP Tutorial for Beginners", summary="Learn PHP basics"
//   - Title matches "php" (10) + "tutorial" (10) = 20
//   - Summary matches "php" (3) = 3
//   - Exact phrase "php tutorial" in title (+5)
//   - Total: 28

// Post B: title="Web Development", summary="Includes PHP tutorial section"
//   - Summary matches "php" (3) + "tutorial" (3) = 6
//   - Total: 6
```

The interface allows replacing this with full-text search (MySQL FULLTEXT, PostgreSQL tsvector, Elasticsearch, etc.) via Preferences.

## Acceptance Criteria
- All requirements have passing tests
- SearchServiceInterface defined for future replacement
- SearchResult DTO defined with relevance score
- SearchService implements basic LIKE search
- Returns SearchResult DTOs sorted by score descending
- Swappable via Preferences to full-text search implementations
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
