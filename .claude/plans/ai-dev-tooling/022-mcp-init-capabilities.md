# Task 022: Implement MCP init + capability negotiation

**Status**: pending
**Depends on**: 021
**Retry count**: 0

## Description
Implement the MCP `initialize` handshake and capability negotiation. Advertise supported features (tools, prompts, resources), protocol version, server info. Implement `tools/list` and `tools/call` dispatch so subsequent tool-implementation tasks can register.

## Context
- Namespace: `Marko\Mcp\Server\McpServer`
- Implements MCP spec initialize lifecycle
- Tools registered via `registerTool(string $name, ToolHandlerInterface $handler, array $schema)`
- Exposes `tools/list` returning all registered tool schemas

## Requirements (Test Descriptions)
- [ ] `it responds to initialize with protocol version and capabilities`
- [ ] `it advertises tools capability when tools are registered`
- [ ] `it returns all registered tools via tools/list`
- [ ] `it dispatches tools/call to the correct handler by name`
- [ ] `it returns JSON-RPC error for unknown tool names`
- [ ] `it validates tool call arguments against declared schema`
- [ ] `it returns tool result as MCP-formatted content`

## Acceptance Criteria
- Feature test exercises the full initialize → tools/list → tools/call flow
- Schema validation errors include field path

## Implementation Notes
(Filled in by programmer during implementation)
