<?php

declare(strict_types=1);

namespace Marko\Lsp\Protocol;

use JsonException;
use Marko\Lsp\Exceptions\LspException;
use Throwable;

class LspProtocol
{
    /** @var array<string, callable> */
    private array $handlers = [];

    private bool $shutdown = false;

    public function __construct(
        private mixed $input,
        private mixed $output,
    ) {}

    public function registerMethod(string $method, callable $handler): void
    {
        $this->handlers[$method] = $handler;
    }

    public function serve(): void
    {
        while (!$this->shutdown && !feof($this->input)) {
            $message = $this->readMessage();
            if ($message === null) {
                break;
            }
            $this->handleMessage($message);
        }
    }

    /** @return string|null Raw JSON body, or null on EOF */
    public function readMessage(): ?string
    {
        $contentLength = 0;

        while (true) {
            $line = fgets($this->input);
            if ($line === false) {
                return null;
            }
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                break;
            }
            if (preg_match('/^Content-Length:\s*(\d+)/i', $line, $m)) {
                $contentLength = (int) $m[1];
            }
        }

        if ($contentLength === 0) {
            return null;
        }

        $body = '';
        $remaining = $contentLength;

        while ($remaining > 0) {
            $chunk = fread($this->input, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $body;
    }

    public function handleMessage(string $jsonBody): void
    {
        try {
            $request = json_decode($jsonBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => -32700, 'message' => 'Parse error'], 'id' => null]);
            return;
        }

        if (!is_array($request) || !isset($request['method'])) {
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid Request'], 'id' => $request['id'] ?? null]);
            return;
        }

        $method = (string) $request['method'];
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;
        $isNotification = !array_key_exists('id', $request);

        if ($method === 'exit') {
            $this->shutdown = true;
            return;
        }

        if (!isset($this->handlers[$method])) {
            if ($isNotification) {
                return;
            }
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => -32601, 'message' => "Method not found: $method"], 'id' => $id]);
            return;
        }

        try {
            $result = ($this->handlers[$method])($params);
            if ($isNotification) {
                return;
            }
            $this->writeResponse(['jsonrpc' => '2.0', 'result' => $result, 'id' => $id]);
        } catch (LspException $e) {
            if ($isNotification) {
                return;
            }
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => $e->getJsonRpcCode(), 'message' => $e->getMessage()], 'id' => $id]);
        } catch (Throwable $e) {
            if ($isNotification) {
                return;
            }
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => -32603, 'message' => 'Internal error'], 'id' => $id]);
        }
    }

    /**
     * @param array<string, mixed> $response
     *
     * @throws JsonException
     */
    public function writeResponse(array $response): void
    {
        $json = json_encode($response, JSON_THROW_ON_ERROR);
        $contentLength = strlen($json);
        fwrite($this->output, "Content-Length: $contentLength\r\n\r\n$json");
    }

    public function isShutdown(): bool
    {
        return $this->shutdown;
    }
}
