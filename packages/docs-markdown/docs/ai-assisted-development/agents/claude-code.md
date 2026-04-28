---
title: Claude Code
description: Set up Marko's AI tooling with Anthropic Claude Code — CLAUDE.md guidelines, MCP tools, and LSP completions.
---

[Claude Code](https://claude.ai/code) is Anthropic's official CLI for Claude. `devai:install` gives it full Marko awareness through a `CLAUDE.md` project file, MCP tool registration, and LSP completions via the `.claude/` plugin directory.

## Prerequisites

- Claude Code installed: `npm install -g @anthropic-ai/claude-code`
- Authenticated: `claude auth login`
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Claude Code detected produces the following files:

```
CLAUDE.md                          # Project guidelines injected from resources/ai/guidelines.md
.claude/commands/                  # Slash commands for common Marko workflows
.claude/plugins/marko/
  lsp.json                         # LSP server registration (marko lsp:serve)
  mcp.json                         # MCP server registration (marko mcp:serve)
```

### CLAUDE.md

The root `CLAUDE.md` receives a merged section containing:

- Marko module conventions (attribute routing, service container, events)
- Available MCP tools and what each one does
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`
- Any skills found under `resources/ai/skills/`

If a `CLAUDE.md` already exists, `devai:install` appends a clearly marked `## Marko` section rather than overwriting the file.

### MCP registration

The `mcp.json` plugin file registers `marko mcp:serve` as an MCP server using stdio transport. Claude Code calls this server whenever it invokes a tool like `search_docs` or `find_event_observers`.

### LSP registration

The `lsp.json` plugin file registers `marko lsp:serve` as a language server. This enables config key completions, template name completions, and translation key completions inside files Claude Code edits.

## Manual verification

1. Open Claude Code in your project root.
2. Run `/marko` — you should see Marko slash commands listed.
3. Ask: `What MCP tools are available?` — Claude should list `search_docs`, `find_event_observers`, `validate_module`, `query_database`.
4. Ask: `Search docs for "routing"` — the `search_docs` tool should return results from Marko documentation.
5. Open a PHP file and type `config('` — you should see Marko config key completions.

## Agent-specific tips

- **Context windows**: Claude Code reads `CLAUDE.md` on every session start. Keep the Marko section concise; detailed skill instructions belong in `resources/ai/skills/`.
- **Slash commands**: The installed commands follow the pattern `/marko:{skill-name}`. Run `/marko:list` to see all registered skills.
- **Re-running install**: Safe to re-run after adding new packages. The installer merges new guidelines without duplicating existing content.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
