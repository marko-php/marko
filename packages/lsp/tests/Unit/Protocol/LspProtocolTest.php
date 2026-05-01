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
