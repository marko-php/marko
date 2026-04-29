---
title: Gemini CLI
description: Set up Marko's AI tooling with Google Gemini CLI — GEMINI.md guidelines, MCP tool registration, and skill distribution.
---

[Gemini CLI](https://github.com/google-gemini/gemini-cli) is Google's open-source agentic terminal tool powered by Gemini models. `devai:install` configures it with a `GEMINI.md` project guidelines file, MCP server registration via the `gemini mcp add` CLI, and Marko skills distributed to `.gemini/skills/`.

## Prerequisites

- Gemini CLI installed: `npm install -g @google/gemini-cli`
- Authenticated: `gemini auth login` (Google account or API key)
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Gemini CLI detected produces the following:

```
GEMINI.md                          # Project guidelines for Gemini CLI
.gemini/skills/                    # Marko skill files distributed for Gemini CLI
AGENTS.md                          # Shared guidelines file (written if not already present)
```

MCP registration is performed via the Gemini CLI (`gemini mcp add`) rather than by writing a config file. Gemini CLI does not implement LSP registration.

### GEMINI.md

The root `GEMINI.md` file receives Marko project guidelines:

- Module structure and naming conventions
- Available MCP tools and their usage patterns
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`

### MCP registration

The installer calls `gemini mcp add -s project -t <transport> <serverName> <command> [args]` to register `marko mcp:serve` as a project-scoped MCP server. Gemini CLI stores this registration in its own configuration; no `.gemini/settings.json` file is written by `devai:install`.

### Skills

Marko skill bundles are written to `.gemini/skills/` so Gemini CLI can reference them during agentic tasks.

### AGENTS.md

If no `AGENTS.md` exists in the project root, `devai:install` creates one with the same guidelines content. If `AGENTS.md` already exists, it is left untouched.

## Manual verification

1. Open a terminal in your project root.
2. Run `gemini` to start an interactive session.
3. Ask: `What MCP tools are registered?` — Marko tools should be listed.
4. Ask: `Search Marko docs for "observers"` — `search_docs` should return results if a docs driver is installed.
5. Verify `GEMINI.md` exists in the project root and `.gemini/skills/` contains skill files.

## Agent-specific tips

- **Large context**: Gemini models support very large context windows. You can include more of your codebase in a single session without hitting limits. The `query_database` tool works well for pulling live schema data into context.
- **`/tools` command**: In an interactive Gemini CLI session, type `/tools` to list all registered MCP tools including the Marko ones.
- **Checkpointing**: Gemini CLI supports session checkpointing. When resuming a checkpoint, the MCP server reconnects automatically on first tool call.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
