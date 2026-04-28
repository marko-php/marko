<?php

declare(strict_types=1);

use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\Exceptions\DocsException;
use Marko\Docs\ValueObject\DocsPage;
use Marko\Docs\ValueObject\DocsQuery;
use Marko\Docs\ValueObject\DocsResult;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\SearchDocsTool;

function makeFakeSearch(array $results = [], ?DocsException $throwException = null): DocsSearchInterface
{
    return new class ($results, $throwException) implements DocsSearchInterface
    {
        public function __construct(private array $r, private ?DocsException $e) {}

        public function search(DocsQuery $q): array
        {
            if ($this->e) {
                throw $this->e;
            }

            return $this->r;
        }

        public function getPage(string $id): DocsPage
        {
            return new DocsPage(id: $id, title: '', content: '', path: '');
        }

        public function listNav(): array
        {
            return [];
        }

        public function driverName(): string
        {
            return 'fake';
        }
    };
}

it('is registered with the MCP server under name search_docs', function (): void {
    $in = fopen('php://memory', 'w+');
    $out = fopen('php://memory', 'w+');
    $protocol = new JsonRpcProtocol($in, $out);
    $server = new McpServer($protocol);

    $tool = SearchDocsTool::definition(makeFakeSearch());
    $server->registerTool($tool);

    $protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]));
    rewind($out);
    $response = json_decode((string) stream_get_contents($out), true);

    $names = array_column($response['result']['tools'], 'name');
    expect($names)->toContain('search_docs');
});

it('validates input requires query string with optional integer limit', function (): void {
    $in = fopen('php://memory', 'w+');
    $out = fopen('php://memory', 'w+');
    $protocol = new JsonRpcProtocol($in, $out);
    $server = new McpServer($protocol);
    $server->registerTool(SearchDocsTool::definition(makeFakeSearch()));

    // Missing required 'query' field
    $protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'search_docs', 'arguments' => []], 'id' => 1]));
    rewind($out);
    $response = json_decode((string) stream_get_contents($out), true);

    expect($response['error'])->not->toBeNull()
        ->and($response['error']['message'])->toContain('Missing required field');
});

it('delegates to DocsSearchInterface::search', function (): void {
    $capturedQuery = null;
    $fakeSearch = new class ($capturedQuery) implements DocsSearchInterface
    {
        public ?DocsQuery $captured = null;

        public function __construct(mixed &$ref) {}

        public function search(DocsQuery $q): array
        {
            $this->captured = $q;

            return [];
        }

        public function getPage(string $id): DocsPage
        {
            return new DocsPage(id: $id, title: '', content: '', path: '');
        }

        public function listNav(): array
        {
            return [];
        }

        public function driverName(): string
        {
            return 'fake';
        }
    };

    $tool = new SearchDocsTool($fakeSearch);
    $tool->handle(['query' => 'routing', 'limit' => 5]);

    expect($fakeSearch->captured)->not->toBeNull()
        ->and($fakeSearch->captured->query)->toBe('routing')
        ->and($fakeSearch->captured->limit)->toBe(5);
});

it('returns results formatted as MCP content blocks', function (): void {
    $results = [
        new DocsResult(pageId: 'routing/basics', title: 'Routing Basics', excerpt: 'How routing works', score: 0.95),
        new DocsResult(pageId: 'routing/advanced', title: 'Advanced Routing', excerpt: 'Advanced topics', score: 0.75),
    ];
    $tool = new SearchDocsTool(makeFakeSearch($results));

    $response = $tool->handle(['query' => 'routing']);

    expect($response)->toHaveKey('content')
        ->and($response['content'])->toHaveCount(1)
        ->and($response['content'][0]['type'])->toBe('text')
        ->and($response['content'][0]['text'])->toContain('Routing Basics')
        ->and($response['content'][0]['text'])->toContain('0.950')
        ->and($response['content'][0]['text'])->toContain('routing/basics')
        ->and($response['content'][0]['text'])->toContain('How routing works')
        ->and($response['content'][0]['text'])->toContain('Advanced Routing');
});

it('handles empty result sets gracefully', function (): void {
    $tool = new SearchDocsTool(makeFakeSearch([]));

    $response = $tool->handle(['query' => 'nonexistent topic']);

    expect($response)->toHaveKey('content')
        ->and($response['content'])->toHaveCount(1)
        ->and($response['content'][0]['type'])->toBe('text')
        ->and($response['content'][0]['text'])->toBe('No documentation matches found.');
});

it('returns an error content block when DocsSearchInterface throws', function (): void {
    $exception = DocsException::searchFailed('index not built');
    $tool = new SearchDocsTool(makeFakeSearch([], $exception));

    $response = $tool->handle(['query' => 'anything']);

    expect($response)->toHaveKey('content')
        ->and($response)->toHaveKey('isError')
        ->and($response['isError'])->toBeTrue()
        ->and($response['content'][0]['type'])->toBe('text')
        ->and($response['content'][0]['text'])->toContain('Error:')
        ->and($response['content'][0]['text'])->toContain('index not built')
        ->and($response['content'][0]['text'])->toContain('Suggestion:');
});
