<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Dto\SearchResult;
use Marko\Blog\Entity\PostInterface;

class SearchService implements SearchServiceInterface
{
    /**
     * @param callable(): array<PostInterface> $postProvider
     */
    public function __construct(
        private readonly mixed $postProvider,
    ) {}

    private const float TITLE_MATCH_SCORE = 10.0;
    private const float SUMMARY_MATCH_SCORE = 3.0;

    /**
     * @return array<SearchResult>
     */
    public function search(
        string $query,
    ): array {
        $posts = ($this->postProvider)();
        $results = [];
        $terms = $this->parseSearchTerms($query);

        foreach ($posts as $post) {
            // Only search published posts
            if (!$post->isPublished()) {
                continue;
            }

            $matchedFields = [];
            $score = 0.0;
            $title = $post->getTitle();
            $summary = $post->getSummary();

            $titleMatched = false;
            $summaryMatched = false;

            foreach ($terms as $term) {
                // Check title
                if (stripos($title, $term) !== false) {
                    $titleMatched = true;
                    $score += self::TITLE_MATCH_SCORE;
                }

                // Check summary
                if ($summary !== null && stripos($summary, $term) !== false) {
                    $summaryMatched = true;
                    $score += self::SUMMARY_MATCH_SCORE;
                }
            }

            if ($titleMatched) {
                $matchedFields[] = 'title';
            }
            if ($summaryMatched) {
                $matchedFields[] = 'summary';
            }

            if (count($matchedFields) > 0) {
                $results[] = new SearchResult($post, $score, $matchedFields);
            }
        }

        // Sort by score descending
        usort($results, fn (SearchResult $a, SearchResult $b): int => $b->score <=> $a->score);

        return $results;
    }

    /**
     * Parse the search query into individual terms.
     *
     * @return array<string>
     */
    private function parseSearchTerms(
        string $query,
    ): array {
        $terms = preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY);

        return $terms !== false ? $terms : [];
    }

    /**
     * @return array{results: array<SearchResult>, total: int}
     */
    public function searchPaginated(
        string $query,
        int $limit,
        int $offset,
    ): array {
        $allResults = $this->search($query);
        $total = count($allResults);

        return [
            'results' => array_slice($allResults, $offset, $limit),
            'total' => $total,
        ];
    }
}
