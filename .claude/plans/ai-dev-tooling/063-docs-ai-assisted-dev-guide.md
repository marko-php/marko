# Task 063: Write AI-assisted development guide for marko.build/docs

**Status**: pending
**Depends on**: 011, 012, 019, 030, 039, 045, 059, 061
**Retry count**: 0

## Description
Write a comprehensive guide in the Marko docs site explaining the AI-assisted development workflow: what `marko/devai` does, what `marko/mcp` and `marko/lsp` provide, how to install and configure for each supported agent (Claude Code, Codex, Cursor, Copilot, Gemini CLI, Junie), how to choose a docs driver, how to extend via third-party `resources/ai/` contributions, and troubleshooting. Serves as the user-facing manual that replaces the end-to-end smoke-test task (manual verification will happen against this guide).

## Context
- Destination: `packages/docs-markdown/docs/` under a new top-level section, suggested path `docs/src/content/docs/ai-assisted-development/` (Astro/Starlight conventions)
- Must land in the same repo that drives marko.build so the live site picks it up automatically
- Target audience: Marko app developers who want AI help, plus package authors who want to contribute guidelines/skills

## Requirements (Test Descriptions)
- [ ] `it adds an "AI-assisted development" top-level section to the docs site navigation`
- [ ] `it includes an overview page explaining the devai/mcp/lsp trio and what each provides`
- [ ] `it includes an installation page covering composer require marko/devai and devai:install flow`
- [ ] `it includes per-agent setup pages for Claude Code, Codex, Cursor, Copilot, Gemini CLI, and Junie`
- [ ] `it includes a docs-driver comparison page explaining docs-fts vs docs-vec tradeoffs and how to switch`
- [ ] `it includes a "contribute guidelines and skills" page for third-party package authors describing the resources/ai/ convention`
- [ ] `it includes a troubleshooting page covering common install failures, ONNX download issues, and agent registration problems`
- [ ] `it includes a manual-verification checklist readers can follow to confirm their setup works end-to-end with at least one agent`
- [ ] `it includes an architecture page explaining how codeindexer feeds both mcp and lsp`
- [ ] `it links each MCP tool and LSP feature to the relevant source package README`

## Acceptance Criteria
- Pages render cleanly on the Astro/Starlight site
- Navigation is discoverable from the docs landing page
- Examples use real Marko code patterns consistent with `.claude/code-standards.md`
- Manual-verification checklist is thorough enough that end-to-end smoke test can be performed by a user following it (replaces the automated smoke-test task)

## Implementation Notes
(Filled in by programmer during implementation)
