# Task 062: Create READMEs for all new packages

**Status**: pending
**Depends on**: 011, 012, 019, 030, 039, 045, 059
**Retry count**: 0

## Description
Create `README.md` for every new package following the Package README Standards in `.claude/code-standards.md`: Title + One-Liner, Overview, Installation, Usage, Customization (if applicable), API Reference. This is the final task — depends on all implementation tasks so READMEs reflect what was actually built.

## Context
- New packages requiring READMEs: `codeindexer`, `mcp`, `lsp`, `docs`, `docs-markdown`, `docs-fts`, `docs-vec`, `devai`
- Renamed packages (`ratelimiter`, `devserver`) keep their existing READMEs but update package name references
- Standards: interface packages describe contracts; driver/implementation packages describe usage

## Requirements (Test Descriptions)
- [ ] `it creates README.md for marko/codeindexer covering usage via IndexCache public API`
- [ ] `it creates README.md for marko/mcp with install + mcp:serve usage + tool list`
- [ ] `it creates README.md for marko/lsp with install + lsp:serve + feature list`
- [ ] `it creates README.md for marko/docs contract package`
- [ ] `it creates README.md for marko/docs-markdown data package`
- [ ] `it creates README.md for marko/docs-fts with install + docs-fts:build`
- [ ] `it creates README.md for marko/docs-vec with install + ONNX + sqlite-vec requirements`
- [ ] `it creates README.md for marko/devai with install + devai:install + supported agents`
- [ ] `it updates README.md for marko/ratelimiter (renamed)`
- [ ] `it updates README.md for marko/devserver (renamed)`

## Acceptance Criteria
- Every README follows the standards exactly
- One-liner states the benefit upfront
- Code examples use real Marko conventions
- API Reference lists public signatures only

## Implementation Notes
(Filled in by programmer during implementation)
