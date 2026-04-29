<?php

declare(strict_types=1);

namespace Marko\Mcp\Server;

use JsonException;
use Marko\Mcp\Exceptions\McpException;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Tools\ToolDefinition;

class McpServer
{
    /**
     * Advertised MCP protocol revision. MCP clients (Claude Code in particular)
     * negotiate strictly: if the client requests `2025-11-25` and we respond
     * with the original `2024-11-05`, Claude Code accepts the connection at
     * the transport level but silently drops every tool from the server's
     * tool surface. Our minimal implementation (initialize, tools/list,
     * tools/call) is forward-compatible across spec revisions — bumping to
     * the latest is the difference between "connected but invisible" and
     * "tools work."
     */
    private const string PROTOCOL_VERSION = '2025-11-25';

    /**
     * Self-reported name in the MCP `initialize` handshake. Must match the
     * name agents use to register the server (e.g. `claude mcp add marko-mcp …`)
     * and must contain no slashes — Claude Code (and other MCP clients) derive
     * the tool-prefix namespace `mcp__<name>__<tool>` from this string, and a
     * slash in <name> renders the resulting tool identifiers invalid, causing
     * the tools to silently disappear from the agent's tool surface.
     */
    private const string SERVER_NAME = 'marko-mcp';

    private const string SERVER_VERSION = '1.0.0';

    /** @var array<string, ToolDefinition> */
    private array $tools = [];

    public function __construct(
        private JsonRpcProtocol $protocol,
    ) {
        $this->registerHandlers();
    }

    public function registerTool(ToolDefinition $tool): void
    {
        $this->tools[$tool->name] = $tool;
    }

    /**
     * @throws JsonException
     */
    public function serve(): void
    {
        $this->protocol->serve();
    }

    private function registerHandlers(): void
    {
        $this->protocol->registerMethod('initialize', fn (array $params) => $this->initialize($params));
        $this->protocol->registerMethod('initialized', fn (array $params) => null);
        $this->protocol->registerMethod('tools/list', fn (array $params) => $this->toolsList());
        $this->protocol->registerMethod('tools/call', fn (array $params) => $this->toolsCall($params));
    }

    /** @param array<string, mixed> $params */
    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => $this->tools !== [] ? (object) [] : null,
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ];
    }

    /** @return array{tools: list<array{name: string, description: string, inputSchema: array}>} */
    private function toolsList(): array
    {
        return [
            'tools' => array_values(array_map(
                fn (ToolDefinition $t) => [
                    'name' => $t->name,
                    'description' => $t->description,
                    'inputSchema' => $this->normalizeInputSchema($t->inputSchema),
                ],
                $this->tools,
            )),
        ];
    }

    /**
     * Force `properties` (and any other JSON-Schema "object" slot) to serialize
     * as a JSON object rather than a JSON array.
     *
     * Background: PHP's json_encode emits `[]` for an empty PHP array, which is
     * a JSON array. JSON Schema requires `properties` to be a JSON object even
     * when empty. MCP clients (Claude Code in particular) validate the
     * tools/list response strictly with Zod and reject the entire payload when
     * any tool's schema has `"properties": []` instead of `"properties": {}`.
     * The whole tool surface goes invisible — connection still succeeds, but
     * "Failed to fetch tools" silently strips every tool from the agent.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function normalizeInputSchema(array $schema): array
    {
        if (isset($schema['properties']) && $schema['properties'] === []) {
            $schema['properties'] = (object) [];
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $params
     * @throws McpException
     */
    private function toolsCall(array $params): array
    {
        $name = $params['name'] ?? null;
        if (!is_string($name) || !isset($this->tools[$name])) {
            throw McpException::methodNotFound("Tool not found: $name");
        }
        $args = $params['arguments'] ?? [];
        if (!is_array($args)) {
            throw McpException::invalidRequest('Tool arguments must be an object');
        }
        $tool = $this->tools[$name];
        $this->validateArgs($args, $tool->inputSchema);

        return $tool->handler->handle($args);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $schema
     * @throws McpException
     */
    private function validateArgs(
        array $args,
        array $schema,
    ): void
    {
        $required = $schema['required'] ?? [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $args)) {
                throw McpException::invalidRequest("Missing required field: $field");
            }
        }
        $properties = $schema['properties'] ?? [];
        foreach ($args as $field => $value) {
            if (!isset($properties[$field]['type'])) {
                continue;
            }
            $expectedType = $properties[$field]['type'];
            $actualType = match (true) {
                is_string($value) => 'string',
                is_int($value) => 'integer',
                is_float($value) => 'number',
                is_bool($value) => 'boolean',
                is_array($value) => 'array',
                default => 'unknown',
            };
            if ($actualType !== $expectedType) {
                throw McpException::invalidRequest("Field '$field' must be of type $expectedType, got $actualType");
            }
        }
    }
}
