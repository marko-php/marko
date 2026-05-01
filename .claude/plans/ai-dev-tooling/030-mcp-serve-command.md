# Task 030: Add mcp:serve CLI command

**Status**: pending
**Depends on**: 022
**Retry count**: 0

## Description
Add the `marko mcp:serve` console command that starts the MCP server on stdio. This is the entry point registered by `marko/devai` in agent configs (e.g., `claude mcp add -s local -t stdio marko-mcp php marko mcp:serve`).

## Context
- Namespace: `Marko\Mcp\Commands\ServeCommand`
- Attribute: `#[Command(name: 'mcp:serve')]`
- Must write only JSON-RPC to stdout; diagnostics to stderr
- Handles SIGTERM/EOF gracefully

## Requirements (Test Descriptions)
- [ ] `it is registered via Command attribute with name mcp:serve`
- [ ] `it boots the MCP server and attaches JsonRpcProtocol to stdio`
- [ ] `it exits 0 on graceful shutdown`
- [ ] `it produces no stdout output other than valid JSON-RPC`
- [ ] `it logs startup diagnostics to stderr only`

## Acceptance Criteria
- Integration test spawns the command as subprocess, sends initialize, receives valid response
- No stdout pollution verified via grep

## Implementation Notes
(Filled in by programmer during implementation)
