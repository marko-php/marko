# Task 001: Research open implementation questions

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Resolve four open implementation questions by reading official Anthropic Claude Code documentation, then record findings + decisions in a research findings document at `.claude/plans/claude-plugins-architecture/research-findings.md`. Subsequent tasks consume this document. No code is written in this task — output is the findings file only.

## Context
- The architecture decisions are already made (three plugins, monorepo-housed). What's open is *how* to wire the parts.
- Authoritative sources to consult:
  - https://code.claude.com/docs/en/plugins
  - https://code.claude.com/docs/en/plugins-reference
  - https://code.claude.com/docs/en/discover-plugins
  - https://code.claude.com/docs/en/plugin-marketplaces
  - https://code.claude.com/docs/en/skills (for the 500-line cap context)
  - Anthropic's own skills repo at `/tmp/anthropics-skills/` (already cloned) for skill anatomy examples
- WebFetch may be blocked for some URLs; if so, fall back to WebSearch and clearly mark which findings came from which source.

## Requirements (Test Descriptions)
- [x] `findings document records whether plugin-shipped LSPs accept relative-path command values, with verbatim quote and source URL`
- [x] `findings document records the .mcp.json schema (top-level structure: flat object keyed by server name vs nested under "mcpServers"), with verbatim quote and source URL`
- [x] `findings document records all valid source types for extraKnownMarketplaces — github, git URL, local path, remote URL — and which apply to a monorepo subdirectory case`
- [x] `findings document records whether a project's .claude/settings.json triggers automatic install prompt on claude startup, or only on /plugin invocation`
- [x] `findings document projects line count for the rewritten create-module SKILL.md (existing content + anti-pattern directives + LSP gate language) and decides whether decomposition into references/ is needed`
- [x] `findings document records the complete plugin.json manifest schema — required vs optional fields — with citation`
- [x] `findings document records whether multiple LSP plugins can register for the same file extension (.php) without conflict, citing official docs or a marketplace example; if docs are silent, an empirical tempdir test is performed and recorded`
- [x] `findings document records the complete marketplace.json schema (required vs optional fields) with citation`
- [x] `findings document records whether Claude Code supports project-local LSP overrides (e.g., .claude/lsp/<name>.json) that merge over plugin-shipped LSP entries; if not, records the alternate shape (lspServers in settings.json)`
- [x] `findings document produces a schemas/ subdirectory containing JSON shape examples (or hand-written JSON Schema) for plugin.json, marketplace.json, .mcp.json, .lsp.json that downstream tasks 002–005 consume verbatim`
- [x] `findings document records whether .latte (Latte template engine) is a recognized LSP language identifier in Claude Code — citing docs or by empirical test — and decides whether to ship .latte in marko-lsp's extensionToLanguage or omit it for v1`

## Acceptance Criteria
- All requirements satisfied as content checks against the findings document
- Every finding has a source URL and verbatim quote where possible
- Findings document includes a "Decisions" section that translates findings into concrete choices for downstream tasks (e.g., "marko-lsp `.lsp.json` will use absolute paths via runtime substitution because relative paths are not supported")
- Document under 600 lines (extended from 400 to accommodate verbatim quotes across seven schema findings)
- No assumptions stated as facts — unknowns are explicitly flagged

## Implementation Notes
Findings written to `research-findings.md` (174 lines, well under the 600-line cap). Schemas committed to `schemas/` subdirectory: `plugin.json.example.json`, `marketplace.json.example.json`, `lsp.json.example.json`, `mcp.json.example.json`, `settings.json.example.json`. Three open unknowns flagged for downstream awareness: multi-LSP-per-extension dedup behavior (F5), Latte LSP availability (F9), and marketplace `strict: false` override semantics (F8). All settled findings cite plugins-reference, plugin-marketplaces, discover-plugins, settings, or the local Anthropic marketplace cache.
