<?php

declare(strict_types=1);

use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\ToolDefinition;
use Marko\Mcp\Tools\ToolHandlerInterface;

beforeEach(function (): void {
    $this->in = fopen('php://memory', 'w+');
    $this->out = fopen('php://memory', 'w+');
    $this->protocol = new JsonRpcProtocol($this->in, $this->out);
    $this->server = new McpServer($this->protocol);
});

it('responds to initialize with protocol version and capabilities', function (): void {
    $this->protocol->handleMessage(
        json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'params' => [], 'id' => 1])
    );
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['protocolVersion'])->toBe('2025-11-25')
        ->and($response['result']['serverInfo']['name'])->toBe('marko-mcp');
});

it('reports a serverInfo.name with no slash so MCP tool prefixes stay valid', function (): void {
    // Regression: when the server reported `marko/mcp`, Claude Code (and other
    // MCP clients) failed to surface its tools because the resulting tool
    // namespace `mcp__marko/mcp__<tool>` is invalid. Tool identifiers must
    // not contain slashes — keep the self-reported name dash-separated.
    $this->protocol->handleMessage(
        json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'params' => [], 'id' => 1])
    );
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['serverInfo']['name'])->not->toContain('/');
});

it('advertises tools capability when tools are registered', function (): void {
    $handler = new class () implements ToolHandlerInterface
    {
        public function handle(array $arguments): array
        {
            return ['content' => [['type' => 'text', 'text' => 'ok']]];
        }
    };
    $this->server->registerTool(new ToolDefinition(
        name: 'echo',
        description: 'Echoes input',
        inputSchema: ['type' => 'object', 'properties' => ['msg' => ['type' => 'string']]],
        handler: $handler,
    ));

    $this->protocol->handleMessage(
        json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'params' => [], 'id' => 1])
    );
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['capabilities']['tools'])->not->toBeNull();
});

it('serializes tools with empty properties as a JSON object, not a JSON array', function (): void {
    // Regression: PHP arrays with no string keys serialize to `[]`, but MCP
    // clients (Claude Code) strictly validate `inputSchema.properties` as an
    // object. Empty `[]` causes "Failed to fetch tools" and the whole tool
    // list disappears. The server must coerce empty properties to `{}`.
    $handler = new class () implements ToolHandlerInterface
    {
        public function handle(array $arguments): array
        {
            return ['content' => [['type' => 'text', 'text' => 'ok']]];
        }
    };
    $this->server->registerTool(new ToolDefinition(
        name: 'no_args_tool',
        description: 'A tool that takes no arguments',
        inputSchema: ['type' => 'object', 'properties' => []],
        handler: $handler,
    ));

    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1]));
    rewind($this->out);
    $rawJson = (string) stream_get_contents($this->out);

    expect($rawJson)->toContain('"properties":{}')
        ->and($rawJson)->not->toContain('"properties":[]');
});

it('returns all registered tools via tools/list', function (): void {
    $handler = new class () implements ToolHandlerInterface
    {
        public function handle(array $arguments): array
        {
            return ['content' => [['type' => 'text', 'text' => 'ok']]];
        }
    };
    $this->server->registerTool(new ToolDefinition(
        name: 'echo',
        description: 'Echoes input',
        inputSchema: ['type' => 'object', 'properties' => ['msg' => ['type' => 'string']]],
        handler: $handler,
    ));

    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 2]));
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['tools'])->toHaveCount(1)
        ->and($response['result']['tools'][0]['name'])->toBe('echo');
});

it('dispatches tools/call to the correct handler by name', function (): void {
    $handler = new class () implements ToolHandlerInterface
    {
        public function handle(array $arguments): array
        {
            return ['content' => [['type' => 'text', 'text' => 'hello ' . ($arguments['name'] ?? '')]]];
        }
    };
    $this->server->registerTool(new ToolDefinition(
        name: 'greet',
        description: 'Greets',
        inputSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']],
        handler: $handler,
    ));

    $this->protocol->handleMessage(
        json_encode(
            ['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'greet', 'arguments' => ['name' => 'world']], 'id' => 3]
        )
    );
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['content'][0]['text'])->toBe('hello world');
});

it('returns JSON-RPC error for unknown tool names', function (): void {
    $this->protocol->handleMessage(
        json_encode(
            ['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'missing', 'arguments' => []], 'id' => 4]
        )
    );
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['error'])->not->toBeNull();
});

it('validates tool call arguments against declared schema', function (): void {
    $handler = new class () implements ToolHandlerInterface
    {
        public function handle(array $arguments): array
        {
            return ['content' => [['type' => 'text', 'text' => 'ok']]];
        }
    };
    $this->server->registerTool(new ToolDefinition(
        name: 'search',
        description: 'Searches',
        inputSchema: ['type' => 'object', 'properties' => ['q' => ['type' => 'string']], 'required' => ['q']],
        handler: $handler,
    ));

    // Missing required field 'q'
    $this->protocol->handleMessage(
        json_encode(
            ['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'search', 'arguments' => []], 'id' => 5]
        )
    );
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['error'])->not->toBeNull()
        ->and($response['error']['message'])->toContain('Missing required field');
});

it('returns tool result as MCP-formatted content', function (): void {
    $handler = new class () implements ToolHandlerInterface
    {
        public function handle(array $arguments): array
        {
            return ['content' => [['type' => 'text', 'text' => 'result text']]];
        }
    };
    $this->server->registerTool(new ToolDefinition(
        name: 'fetch',
        description: 'Fetches data',
        inputSchema: ['type' => 'object', 'properties' => []],
        handler: $handler,
    ));

    $this->protocol->handleMessage(
        json_encode(
            ['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'fetch', 'arguments' => []], 'id' => 6]
        )
    );
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['content'])->toHaveCount(1)
        ->and($response['result']['content'][0]['type'])->toBe('text')
        ->and($response['result']['content'][0]['text'])->toBe('result text');
});
