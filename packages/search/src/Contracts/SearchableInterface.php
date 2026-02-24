<?php

declare(strict_types=1);

namespace Marko\Search\Contracts;

interface SearchableInterface
{
    /**
     * Get the searchable fields and their boost weights.
     *
     * Returns an array of field names mapped to float weights.
     * Higher weights give that field more relevance in search results.
     *
     * Example: ['title' => 2.0, 'content' => 1.0, 'tags' => 1.5]
     *
     * @return array<string, float>
     */
    public function getSearchableFields(): array;
}
