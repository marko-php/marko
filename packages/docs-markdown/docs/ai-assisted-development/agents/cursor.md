---
title: Cursor
description: Set up Marko's AI tooling with Cursor — .cursor/rules/marko.mdc guidelines and MCP tools.
---

[Cursor](https://cursor.sh) is an AI-first code editor built on VS Code. `devai:install` configures it with a `.cursor/rules/marko.mdc` project rules file and MCP server registration.

## Prerequisites

- Cursor installed: [cursor.sh/download](https://cursor.sh/download)
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Cursor detected produces the following files:

```
.cursor/rules/marko.mdc            # Project guidelines and conventions (alwaysApply: true)
.cursor/mcp.json                   # MCP server registration (marko mcp:serve)
AGENTS.md                          # Shared guidelines file (written if not already present)
```

Cursor does not implement LSP registration and no skills are distributed for Cursor.

### .cursor/rules/marko.mdc

The `.cursor/rules/marko.mdc` file is written with `alwaysApply: true` frontmatter so it is always included in Cursor's context. It contains merged Marko guidelines:

- Module structure and naming conventions
- Available MCP tools and usage patterns
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`

### MCP registration

The `.cursor/mcp.json` file registers `marko mcp:serve` as an MCP server using the `mcpServers` key. Cursor's AI can invoke tools like `find_event_observers` and `validate_module` during chat and Composer sessions.

### AGENTS.md

If no `AGENTS.md` exists in the project root, `devai:install` creates one with the same guidelines content. If `AGENTS.md` already exists, it is left untouched.

## Manual verification

1. Open your project in Cursor.
2. Open the AI chat panel and ask: `What Marko MCP tools are available?`
3. Ask: `Search Marko docs for "events"` — `search_docs` should return results if a docs driver is installed.
4. Confirm `.cursor/rules/marko.mdc` exists and contains `alwaysApply: true`.
5. Check that `.cursor/mcp.json` references `marko mcp:serve` under `mcpServers`.

## Agent-specific tips

- **Composer sessions**: Use Cursor's Composer for multi-file Marko tasks. The `validate_module` tool lets it verify its own output before presenting it to you.
- **MCP in chat**: Type `@mcp` in the Cursor chat to surface available MCP tools. The Marko tools appear automatically once `.cursor/mcp.json` is in place.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
