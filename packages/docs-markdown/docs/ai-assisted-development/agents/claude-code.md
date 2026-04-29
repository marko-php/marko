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

Running `marko devai:install` with Claude Code detected produces the following files and registrations:

```
AGENTS.md                          # Merged Marko guidelines (shared across agents)
CLAUDE.md                          # Includes @AGENTS.md and Claude-specific notes
.claude/plugins/marko/
  .lsp.json                        # LSP server registration (marko lsp:serve)
.claude/skills/                    # Skill bundles from resources/ai/skills/
```

The MCP server is registered via the `claude mcp add` CLI call rather than a written config file. Claude Code manages its own MCP registry.

### AGENTS.md and CLAUDE.md

The installer writes the merged Marko guidelines to `AGENTS.md`. The `CLAUDE.md` file references it via `@AGENTS.md` and adds a short note explaining that Marko skills under `.claude/skills/` are auto-loaded by Claude Code when their description matches the conversation. If `CLAUDE.md` already exists it is overwritten; the `AGENTS.md` body contains all the substantive content.

### Skills

Marko ships task-oriented skills (e.g. `marko-create-module`, `marko-create-plugin`) into `.claude/skills/{skill-name}/SKILL.md`. Claude Code discovers them automatically by reading each skill's frontmatter `description` field; **skills are not manually slash-invocable**. When the user's request matches a skill's description, Claude loads it and follows the contained workflow.

### MCP registration

The installer runs `claude mcp add -s local -t stdio marko-mcp php marko mcp:serve` to register the MCP server with Claude Code's local project scope. Claude Code calls this server whenever it invokes a tool like `search_docs` or `find_event_observers`.

### LSP registration

The `.claude/plugins/marko/.lsp.json` file registers `marko lsp:serve` as a language server. This enables config key completions, template name completions, and translation key completions inside files Claude Code edits.

## Manual verification

1. Open Claude Code in your project root.
2. Confirm the MCP server is registered: run `/mcp` in Claude Code — `marko-mcp` should appear in the list.
3. Ask Claude: `What MCP tools are available from marko-mcp?` — it should enumerate tools like `search_docs`, `find_event_observers`, `validate_module`, `last_error`.
4. Ask: `Search the Marko docs for routing.` — Claude should call `search_docs` and return results.
5. Trigger a skill by intent (not by slash): ask `Create a new Marko module called acme/payment.` — Claude should load the `marko-create-module` skill and follow it.
6. Open a PHP file and type `config('` — your editor (with `marko lsp:serve` connected) should show Marko config key completions.

## Agent-specific tips

- **Context windows**: Claude Code reads `CLAUDE.md` on every session start. Keep it lean — substantive guidance belongs in `AGENTS.md` (project-wide) or `resources/ai/skills/{name}/SKILL.md` (task-specific, lazily loaded).
- **Skills are auto-discovered**: There are no `/marko:*` slash commands. Skills load when Claude judges the user's request matches a skill's `description` frontmatter, so write descriptions that name the trigger conditions explicitly.
- **Re-running install**: Safe to re-run after adding new packages. The installer merges new guidelines without duplicating existing content.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
