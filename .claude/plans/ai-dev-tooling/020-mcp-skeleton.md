# Task 020: Create marko/mcp package skeleton

**Status**: pending
**Depends on**: 011, 013
**Retry count**: 0

## Description
Create the `marko/mcp` package skeleton. This is the MCP (Model Context Protocol) server that exposes Marko's code index + runtime introspection to any MCP-capable AI agent (Claude Code, Codex, Cursor, etc.) over stdio JSON-RPC.

## Context
- Path: `packages/mcp/`
- Namespace: `Marko\Mcp\`
- Composer requires: `marko/core`, `marko/codeindexer`, `marko/docs`
- Protocol reference: Model Context Protocol stdio transport (Anthropic spec)

## Requirements (Test Descriptions)
- [ ] `it has composer.json with name marko/mcp and dependencies on codeindexer and docs`
- [ ] `it has src tests/Unit tests/Feature directories with Pest bootstrap`
- [ ] `it autoloads cleanly with composer dump-autoload`
- [ ] `it has module.php with empty bindings (tools register later)`

## Acceptance Criteria
- Skeleton present, composer autoload works
- Placeholder tests run

## Implementation Notes
(Filled in by programmer during implementation)
