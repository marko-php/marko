<?php

declare(strict_types=1);

use Marko\Lsp\Protocol\LspProtocol;

beforeEach(function (): void {
    $this->in = fopen('php://memory', 'w+');
    $this->out = fopen('php://memory', 'w+');
    $this->protocol = new LspProtocol($this->in, $this->out);
});

it('parses Content-Length framed JSON-RPC messages from input', function (): void {
    $body = '{"jsonrpc":"2.0","method":"echo","params":{"x":1},"id":1}';
    $framed = 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;
    fwrite($this->in, $framed);
    rewind($this->in);

    $msg = $this->protocol->readMessage();
    expect($msg)->toBe($body);
});

it('writes Content-Length framed JSON-RPC responses to output', function (): void {
    $this->protocol->writeResponse(['jsonrpc' => '2.0', 'result' => 'ok', 'id' => 1]);
    rewind($this->out);
    $output = (string) stream_get_contents($this->out);
    expect($output)->toContain('Content-Length: ')
        ->and($output)->toContain('"result":"ok"');
});

it('invokes the registered handler for a known method', function (): void {
    $this->protocol->registerMethod('echo', fn (array $p) => $p);
    $this->protocol->handleMessage('{"jsonrpc":"2.0","method":"echo","params":{"hello":"world"},"id":1}');
    rewind($this->out);
    $output = (string) stream_get_contents($this->out);
    $body = substr($output, strpos($output, "\r\n\r\n") + 4);
    $resp = json_decode($body, true);
    expect($resp['result'])->toBe(['hello' => 'world']);
});

it('returns JSON-RPC error for unknown methods', function (): void {
    $this->protocol->handleMessage('{"jsonrpc":"2.0","method":"unknown/method","id":2}');
    rewind($this->out);
    $output = (string) stream_get_contents($this->out);
    $body = substr($output, strpos($output, "\r\n\r\n") + 4);
    $resp = json_decode($body, true);
    expect($resp['error']['code'])->toBe(-32601)
        ->and($resp['id'])->toBe(2);
});

it('supports notifications without responses', function (): void {
    $called = false;
    $this->protocol->registerMethod('$/notification', function () use (&$called): null {
        $called = true;

        return null;
    });
    // Notifications have no "id" field
    $this->protocol->handleMessage('{"jsonrpc":"2.0","method":"$/notification","params":{}}');
    rewind($this->out);
    $output = (string) stream_get_contents($this->out);
    expect($called)->toBeTrue()
        ->and($output)->toBe('');
});

it('handles graceful shutdown on exit notification', function (): void {
    $this->protocol->handleMessage('{"jsonrpc":"2.0","method":"exit"}');
    expect($this->protocol->isShutdown())->toBeTrue();
});

it('does not terminate the serve loop on a malformed frame', function (): void {
    $validBody = '{"jsonrpc":"2.0","method":"echo","params":{},"id":1}';
    $validFrame = 'Content-Length: ' . strlen($validBody) . "\r\n\r\n" . $validBody;

    // Malformed frame: Content-Length: 0, then valid frame, then EOF
    $malformedFrame = "Content-Length: 0\r\n\r\n";

    fwrite($this->in, $malformedFrame . $validFrame);
    rewind($this->in);

    $handled = false;
    $this->protocol->registerMethod('echo', function () use (&$handled): array {
        $handled = true;

        return [];
    });

    $this->protocol->serve();

    expect($handled)->toBeTrue();
});

it('writes a JSON-RPC parse error for a malformed frame', function (): void {
    $validBody = '{"jsonrpc":"2.0","method":"echo","params":{},"id":1}';
    $validFrame = 'Content-Length: ' . strlen($validBody) . "\r\n\r\n" . $validBody;
    $malformedFrame = "Content-Length: 0\r\n\r\n";

    fwrite($this->in, $malformedFrame . $validFrame);
    rewind($this->in);

    $this->protocol->registerMethod('echo', fn (): array => []);
    $this->protocol->serve();

    rewind($this->out);
    $output = (string) stream_get_contents($this->out);

    // First response should be a parse error
    $firstFrameEnd = strpos($output, "\r\n\r\n");
    $firstBody = substr($output, $firstFrameEnd + 4);
    $firstResponseEnd = strpos($firstBody, 'Content-Length:');
    $firstResponseJson = $firstResponseEnd !== false ? substr($firstBody, 0, $firstResponseEnd) : $firstBody;

    $resp = json_decode(trim($firstResponseJson), true);
    expect($resp['error']['code'])->toBe(-32700)
        ->and($resp['id'])->toBeNull();
});

it('processes a valid message that follows a malformed frame', function (): void {
    $validBody = '{"jsonrpc":"2.0","method":"echo","params":{"x":42},"id":5}';
    $validFrame = 'Content-Length: ' . strlen($validBody) . "\r\n\r\n" . $validBody;
    $malformedFrame = "Content-Length: 0\r\n\r\n";

    fwrite($this->in, $malformedFrame . $validFrame);
    rewind($this->in);

    $this->protocol->registerMethod('echo', fn (array $p): array => $p);
    $this->protocol->serve();

    rewind($this->out);
    $output = (string) stream_get_contents($this->out);

    // Find the second JSON-RPC frame (after the parse error frame)
    $firstEnd = strpos($output, "\r\n\r\n");
    $firstBodyStart = $firstEnd + 4;

    // Parse the first response length from Content-Length header
    $firstHeader = substr($output, 0, $firstEnd);
    preg_match('/Content-Length:\s*(\d+)/i', $firstHeader, $m);
    $firstBodyLen = (int) $m[1];
    $secondFrameStart = $firstBodyStart + $firstBodyLen;

    $secondFrameEnd = strpos($output, "\r\n\r\n", $secondFrameStart);
    $secondBody = substr($output, $secondFrameEnd + 4);

    $resp = json_decode($secondBody, true);
    expect($resp['result'])->toBe(['x' => 42])
        ->and($resp['id'])->toBe(5);
});

it('ends the serve loop on genuine end of input', function (): void {
    // Empty stream = immediate EOF, serve loop should exit cleanly
    rewind($this->in);

    $this->protocol->serve();

    // If we get here without hanging, the loop ended on EOF
    expect(true)->toBeTrue();
});
