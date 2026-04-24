<?php

declare(strict_types=1);

use Marko\Mcp\Protocol\JsonRpcProtocol;

beforeEach(function (): void {
    $this->in = fopen('php://memory', 'w+');
    $this->out = fopen('php://memory', 'w+');
    $this->protocol = new JsonRpcProtocol($this->in, $this->out);
});

it('parses JSON-RPC 2.0 requests from input stream', function (): void {
    $this->protocol->registerMethod('ping', fn (array $params) => ['pong' => true]);

    fwrite($this->in, json_encode(['jsonrpc' => '2.0', 'method' => 'ping', 'params' => [], 'id' => 1]) . "\n");
    rewind($this->in);

    $this->protocol->serve();

    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response)->toMatchArray(['jsonrpc' => '2.0', 'result' => ['pong' => true], 'id' => 1]);
});

it('invokes the registered handler for a known method', function (): void {
    $this->protocol->registerMethod('echo', fn (array $params) => $params);

    $request = json_encode(['jsonrpc' => '2.0', 'method' => 'echo', 'params' => ['hello' => 'world'], 'id' => 1]);
    $this->protocol->handleMessage($request);

    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response)->toMatchArray(['jsonrpc' => '2.0', 'result' => ['hello' => 'world'], 'id' => 1]);
});

it('returns JSON-RPC error for unknown methods', function (): void {
    $request = json_encode(['jsonrpc' => '2.0', 'method' => 'nonexistent', 'id' => 2]);
    $this->protocol->handleMessage($request);

    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['error']['code'])->toBe(-32601)
        ->and($response['id'])->toBe(2);
});

it('returns JSON-RPC error for malformed requests', function (): void {
    $this->protocol->handleMessage('not valid json {{{');

    rewind($this->out);
    $response = json_decode((string) stream_get_contents($this->out), true);

    expect($response['error']['code'])->toBe(-32700)
        ->and($response['id'])->toBeNull();
});

it('supports notifications (no id, no response)', function (): void {
    $invoked = false;
    $this->protocol->registerMethod('notify', function (array $params) use (&$invoked): null {
        $invoked = true;

        return null;
    });

    // Notification: no "id" key at all
    $notification = json_encode(['jsonrpc' => '2.0', 'method' => 'notify', 'params' => []]);
    $this->protocol->handleMessage($notification);

    rewind($this->out);
    $written = stream_get_contents($this->out);

    expect($invoked)->toBeTrue()
        ->and($written)->toBe('');
});

it('serializes results back to output stream as JSON-RPC responses', function (): void {
    $this->protocol->registerMethod('add', fn (array $params) => ['sum' => $params['a'] + $params['b']]);

    $request = json_encode(['jsonrpc' => '2.0', 'method' => 'add', 'params' => ['a' => 3, 'b' => 4], 'id' => 99]);
    $this->protocol->handleMessage($request);

    rewind($this->out);
    $raw = stream_get_contents($this->out);
    $response = json_decode(trim($raw), true);

    expect($raw)->toEndWith("\n")
        ->and($response['jsonrpc'])->toBe('2.0')
        ->and($response['result']['sum'])->toBe(7)
        ->and($response['id'])->toBe(99);
});

it('frames messages correctly per MCP transport spec', function (): void {
    // MCP spec: newline-delimited JSON — one complete JSON object per line, no Content-Length header
    $this->protocol->registerMethod('greet', fn (array $params) => ['greeting' => 'hello']);

    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'greet', 'params' => [], 'id' => 1]));
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'greet', 'params' => [], 'id' => 2]));

    rewind($this->out);
    $raw = stream_get_contents($this->out);
    $lines = explode("\n", trim($raw));

    // Two messages → two newline-delimited JSON lines
    expect(count($lines))->toBe(2);

    $first = json_decode($lines[0], true);
    $second = json_decode($lines[1], true);

    expect($first['id'])->toBe(1)
        ->and($second['id'])->toBe(2);

    // No Content-Length framing (no colon-separated headers before JSON)
    expect($lines[0])->toStartWith('{')
        ->and($lines[1])->toStartWith('{');
});

it('handles graceful shutdown on EOF', function (): void {
    // Empty input stream — serve() should return immediately without hanging
    // (input is empty, fgets returns false, loop exits)
    $returned = false;
    $this->protocol->serve();
    $returned = true;

    expect($returned)->toBeTrue();
});
