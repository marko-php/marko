<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\Exceptions\DocsException;
use Marko\Docs\ValueObject\DocsQuery;

readonly class SearchDocsTool implements ToolHandlerInterface
{
    public function __construct(
        private DocsSearchInterface $search,
    ) {}

    public static function definition(DocsSearchInterface $search): ToolDefinition
    {
        return new ToolDefinition(
            name: 'search_docs',
            description: 'Search Marko Framework documentation. Returns ranked results with excerpts.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results (default 10)'],
                ],
                'required' => ['query'],
            ],
            handler: new self($search),
        );
    }

    public function handle(array $arguments): array
    {
        try {
            $results = $this->search->search(new DocsQuery(
                query: (string) $arguments['query'],
                limit: (int) ($arguments['limit'] ?? 10),
            ));

            if ($results === []) {
                return ['content' => [['type' => 'text', 'text' => 'No documentation matches found.']]];
            }

            $formatted = array_map(fn ($r) => sprintf(
                "## %s (score: %.3f)\nID: %s\n\n%s\n",
                $r->title,
                $r->score,
                $r->pageId,
                $r->excerpt,
            ), $results);

            return ['content' => [['type' => 'text', 'text' => implode("\n---\n", $formatted)]]];
        } catch (DocsException $e) {
            return ['content' => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage() . "\nSuggestion: " . $e->getSuggestion()]], 'isError' => true];
        }
    }
}
