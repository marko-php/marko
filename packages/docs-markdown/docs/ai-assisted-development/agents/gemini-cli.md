---
title: Gemini CLI
description: Set up Marko's AI tooling with Google Gemini CLI — GEMINI.md guidelines and MCP tool registration.
---

[Gemini CLI](https://github.com/google-gemini/gemini-cli) is Google's open-source agentic terminal tool powered by Gemini models. `devai:install` configures it with a `GEMINI.md` project guidelines file and MCP server registration.

## Prerequisites

- Gemini CLI installed: `npm install -g @google/gemini-cli`
- Authenticated: `gemini auth login` (Google account or API key)
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Gemini CLI detected produces the following files:

```
GEMINI.md                          # Project guidelines for Gemini CLI
.gemini/settings.json              # MCP server registration (marko mcp:serve)
```

### GEMINI.md

The root `GEMINI.md` file receives merged Marko guidelines:

- Module structure and naming conventions
- Available MCP tools and their usage patterns
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`
- Skill instructions from `resources/ai/skills/`

If `GEMINI.md` already exists, `devai:install` appends a `## Marko` section without overwriting existing content.

### MCP registration

The `.gemini/settings.json` file adds `marko mcp:serve` to the `mcpServers` array using stdio transport. Gemini CLI will call this server for tools like `search_docs`, `find_event_observers`, `validate_module`, and `query_database`.

Example configuration written by the installer:

```json
{
  "mcpServers": {
    "marko": {
      "command": "marko",
      "args": ["mcp:serve"],
      "transport": "stdio"
    }
  }
}
```

## Manual verification

1. Open a terminal in your project root.
2. Run `gemini` to start an interactive session.
3. Ask: `What MCP tools are registered?` — Marko tools should be listed.
4. Ask: `Search Marko docs for "observers"` — `search_docs` should return results.
5. Verify `.gemini/settings.json` contains the `marko` entry under `mcpServers`.

## Agent-specific tips

- **Large context**: Gemini models support very large context windows. You can include more of your codebase in a single session without hitting limits. The `query_database` tool works well for pulling live schema data into context.
- **`/tools` command**: In an interactive Gemini CLI session, type `/tools` to list all registered MCP tools including the Marko ones.
- **Checkpointing**: Gemini CLI supports session checkpointing. When resuming a checkpoint, the MCP server reconnects automatically on first tool call.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
