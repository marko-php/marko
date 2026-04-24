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
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'params' => [], 'id' => 1]));
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['protocolVersion'])->toBe('2024-11-05')
        ->and($response['result']['serverInfo']['name'])->toBe('marko/mcp');
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

    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'params' => [], 'id' => 1]));
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['capabilities']['tools'])->not->toBeNull();
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

    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'greet', 'arguments' => ['name' => 'world']], 'id' => 3]));
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['content'][0]['text'])->toBe('hello world');
});

it('returns JSON-RPC error for unknown tool names', function (): void {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'missing', 'arguments' => []], 'id' => 4]));
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
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'search', 'arguments' => []], 'id' => 5]));
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

    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => ['name' => 'fetch', 'arguments' => []], 'id' => 6]));
    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['result']['content'])->toHaveCount(1)
        ->and($response['result']['content'][0]['type'])->toBe('text')
        ->and($response['result']['content'][0]['text'])->toBe('result text');
});
