<?php

declare(strict_types=1);

namespace Marko\Mcp\Protocol;

use JsonException;
use Marko\Mcp\Exceptions\McpException;
use Throwable;

class JsonRpcProtocol
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct(
        private mixed $input,
        private mixed $output,
    ) {}

    public function registerMethod(string $method, callable $handler): void
    {
        $this->handlers[$method] = $handler;
    }

    /**
     * Run loop — reads, dispatches, writes until EOF
     *
     * @throws JsonException
     */
    public function serve(): void
    {
        while (!feof($this->input)) {
            $line = fgets($this->input);
            if ($line === false) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $this->handleMessage($line);
        }
    }

    /**
     * Public for testing — handle one message string
     *
     * @throws JsonException
     */
    public function handleMessage(string $jsonLine): void
    {
        try {
            $request = json_decode($jsonLine, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => -32700, 'message' => 'Parse error: ' . $e->getMessage()], 'id' => null]);

            return;
        }

        if (!is_array($request) || !isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0' || !isset($request['method'])) {
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid Request'], 'id' => $request['id'] ?? null]);

            return;
        }

        $method = (string) $request['method'];
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;
        $isNotification = !array_key_exists('id', $request);

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
        } catch (McpException $e) {
            if ($isNotification) {
                return;
            }
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => $e->getJsonRpcCode(), 'message' => $e->getMessage()], 'id' => $id]);
        } catch (Throwable $e) {
            if ($isNotification) {
                return;
            }
            $this->writeResponse(['jsonrpc' => '2.0', 'error' => ['code' => -32603, 'message' => 'Internal error: ' . $e->getMessage()], 'id' => $id]);
        }
    }

    /**
     * @param array<string, mixed> $response
     * @throws JsonException
     */
    private function writeResponse(array $response): void
    {
        $json = json_encode($response, JSON_THROW_ON_ERROR);
        fwrite($this->output, $json . "\n");
    }
}
