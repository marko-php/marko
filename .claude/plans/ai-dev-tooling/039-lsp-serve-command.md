# Task 039: Add lsp:serve CLI command

**Status**: pending
**Depends on**: 033
**Retry count**: 0

## Description
Add the `marko lsp:serve` console command that starts the LSP server on stdio. Registered by `marko/devai` in agent LSP configs (e.g., Claude Code `.lsp.json`).

## Context
- Namespace: `Marko\Lsp\Commands\ServeCommand`
- Attribute: `#[Command(name: 'lsp:serve')]`
- Must write only LSP-framed JSON-RPC to stdout; diagnostics to stderr

## Requirements (Test Descriptions)
- [ ] `it is registered via Command attribute with name lsp:serve`
- [ ] `it boots the LSP server and attaches LspProtocol to stdio`
- [ ] `it exits 0 on graceful shutdown after exit notification`
- [ ] `it produces no stdout output other than valid LSP messages`
- [ ] `it logs startup diagnostics to stderr only`

## Acceptance Criteria
- Integration test spawns the command as subprocess, sends initialize, receives valid response
- No stdout pollution verified via grep

## Implementation Notes
(Filled in by programmer during implementation)
