# Task 032: Implement LSP JSON-RPC stdio protocol handler

**Status**: pending
**Depends on**: 031
**Retry count**: 0

## Description
Implement `LspProtocol` — LSP's `Content-Length:`-framed JSON-RPC 2.0 transport over stdio. Reads framed messages from stdin, dispatches to registered method handlers, writes framed responses to stdout.

## Context
- Namespace: `Marko\Lsp\Protocol\LspProtocol`
- Transport: LSP spec — `Content-Length: N\r\n\r\n{...json...}`
- Handlers registered via `registerMethod(string $method, callable $handler)`
- Shares no code with `marko/mcp`'s transport (MCP spec and LSP spec differ in framing)

## Requirements (Test Descriptions)
- [ ] `it parses Content-Length framed JSON-RPC messages from input`
- [ ] `it writes Content-Length framed JSON-RPC responses to output`
- [ ] `it invokes the registered handler for a known method`
- [ ] `it returns JSON-RPC error for unknown methods`
- [ ] `it supports notifications without responses`
- [ ] `it handles graceful shutdown on exit notification`

## Acceptance Criteria
- LSP client fixture test round-trips a simple request/response
- Framing exactly matches spec (no stray bytes, correct content length)

## Implementation Notes
(Filled in by programmer during implementation)
