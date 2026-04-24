<?php

declare(strict_types=1);

use Marko\Lsp\Protocol\LspProtocol;
use Marko\Lsp\Server\LspServer;

beforeEach(function () {
    $this->in = fopen('php://memory', 'w+');
    $this->out = fopen('php://memory', 'w+');
    $this->protocol = new LspProtocol($this->in, $this->out);
    $this->server = new LspServer($this->protocol);
});

function readResponse($out): array
{
    rewind($out);
    $raw = (string) stream_get_contents($out);
    $body = substr($raw, strpos($raw, "\r\n\r\n") + 4);
    return json_decode($body, true);
}

it('responds to initialize with server capabilities', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'params' => [], 'id' => 1]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities'])->toBeArray()
        ->and($response['result']['serverInfo'])->toBeArray();
});

it('declares completionProvider with trigger characters', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 2]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities']['completionProvider']['triggerCharacters'])
        ->toContain('"')
        ->toContain("'")
        ->toContain(':')
        ->toContain('.');
});

it('declares definitionProvider capability', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 3]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities']['definitionProvider'])->toBeTrue();
});

it('declares hoverProvider capability', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 4]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities']['hoverProvider'])->toBeTrue();
});

it('declares codeLensProvider capability', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 5]));
    $response = readResponse($this->out);
    expect($response['result']['capabilities']['codeLensProvider'])->toBeArray();
});

it('responds to shutdown with null and exits cleanly on exit notification', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'shutdown', 'id' => 6]));
    expect($this->server->isShuttingDown())->toBeTrue();
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'exit']));
    expect($this->protocol->isShutdown())->toBeTrue();
});

it('returns server info including name and version', function () {
    $this->protocol->handleMessage(json_encode(['jsonrpc' => '2.0', 'method' => 'initialize', 'id' => 7]));
    $response = readResponse($this->out);
    expect($response['result']['serverInfo']['name'])->toBe('marko-lsp')
        ->and($response['result']['serverInfo']['version'])->toBeString();
});
