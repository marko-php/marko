# Task 033: Implement LSP initialize + capability negotiation

**Status**: pending
**Depends on**: 032
**Retry count**: 0

## Description
Implement the LSP `initialize` and `initialized` handshake. Advertise server capabilities: completionProvider (with trigger characters `'`, `"`, `:`, `.`), definitionProvider, hoverProvider, codeLensProvider, and diagnostic push.

## Context
- Namespace: `Marko\Lsp\Server\LspServer`
- Registers handlers for `initialize`, `initialized`, `shutdown`, `exit`
- Trigger characters drive completion on string literal start and dot notation

## Requirements (Test Descriptions)
- [ ] `it responds to initialize with server capabilities`
- [ ] `it declares completionProvider with trigger characters`
- [ ] `it declares definitionProvider capability`
- [ ] `it declares hoverProvider capability`
- [ ] `it declares codeLensProvider capability`
- [ ] `it responds to shutdown with null and exits cleanly on exit notification`
- [ ] `it returns server info including name and version`

## Acceptance Criteria
- Feature test exercises initialize → shutdown lifecycle
- Capabilities JSON matches LSP 3.17 spec exactly

## Implementation Notes
(Filled in by programmer during implementation)
