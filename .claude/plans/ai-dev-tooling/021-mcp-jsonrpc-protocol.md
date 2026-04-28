# Task 021: Implement MCP JSON-RPC stdio protocol handler

**Status**: pending
**Depends on**: 020
**Retry count**: 0

## Description
Implement `JsonRpcProtocol` — a pure stdio JSON-RPC 2.0 transport for MCP. Reads newline-delimited JSON from stdin, dispatches to registered method handlers, writes responses to stdout. No HTTP, no sockets.

## Context
- Namespace: `Marko\Mcp\Protocol\JsonRpcProtocol`
- Transport: stdio with LSP-style `Content-Length:` framing OR newline-delimited JSON (MCP spec prescribes — follow the spec)
- Handlers registered via `registerMethod(string $method, callable $handler)` where handler takes params and returns result or throws McpException

## Requirements (Test Descriptions)
- [ ] `it parses JSON-RPC 2.0 requests from input stream`
- [ ] `it invokes the registered handler for a known method`
- [ ] `it returns JSON-RPC error for unknown methods`
- [ ] `it returns JSON-RPC error for malformed requests`
- [ ] `it supports notifications (no id, no response)`
- [ ] `it serializes results back to output stream as JSON-RPC responses`
- [ ] `it frames messages correctly per MCP transport spec`
- [ ] `it handles graceful shutdown on EOF`

## Acceptance Criteria
- Pair of stream fixtures in Pest feature tests
- Standards-compliant JSON-RPC 2.0 responses
- No stdout pollution from debug output (debug goes to stderr)

## Implementation Notes
(Filled in by programmer during implementation)
