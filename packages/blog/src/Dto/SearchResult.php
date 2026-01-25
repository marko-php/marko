<?php

declare(strict_types=1);

namespace Marko\Blog\Dto;

use Marko\Blog\Entity\PostInterface;

/**
 * Immutable DTO representing a single search result with relevance score.
 */
readonly class SearchResult
{
    /**
     * @param PostInterface $post The matching post
     * @param float $score Relevance score (higher = more relevant)
     * @param array<string> $matchedFields Fields where the term was found ['title', 'summary']
     */
    public function __construct(
        public PostInterface $post,
        public float $score,
        public array $matchedFields,
    ) {}

    public function matchedInTitle(): bool
    {
        return in_array('title', $this->matchedFields, true);
    }

    public function matchedInSummary(): bool
    {
        return in_array('summary', $this->matchedFields, true);
    }
}
