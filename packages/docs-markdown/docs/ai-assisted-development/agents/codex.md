---
title: Codex
description: Set up Marko's AI tooling with OpenAI Codex CLI — AGENTS.md guidelines, MCP tool registration, and skill distribution.
---

[OpenAI Codex CLI](https://github.com/openai/codex) is OpenAI's agentic coding tool. `devai:install` configures it with an `AGENTS.md` project guidelines file, MCP server registration via the `codex mcp add` CLI, and Marko skills distributed to `.agents/skills/`.

## Prerequisites

- Codex CLI installed: `npm install -g @openai/codex`
- Authenticated: `codex auth login`
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Codex detected produces the following:

```
AGENTS.md                          # Project guidelines for Codex
.agents/skills/                    # Marko skill files distributed for Codex
```

MCP registration is performed via the Codex CLI (`codex mcp add`) rather than by writing a config file.

### AGENTS.md

The root `AGENTS.md` receives Marko project guidelines:

- Marko module conventions and project structure overview
- Available MCP tools and their descriptions
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`

### MCP registration

The installer calls `codex mcp add <serverName> -- <command> [args]` to register `marko mcp:serve` as an MCP server. Codex stores this registration in its own configuration; no `.codex/mcp.json` file is written by `devai:install`.

### Skills

Marko skill bundles are written to `.agents/skills/` so Codex can reference them during agentic tasks.

## Manual verification

1. Open a terminal in your project root.
2. Run `codex "What Marko MCP tools are available?"` — Codex should list the registered tools from `marko/mcp`.
3. Run `codex "Search docs for routing"` — the `search_docs` tool should return Marko documentation results.
4. Verify `AGENTS.md` exists in the project root and `.agents/skills/` contains skill files.

## Agent-specific tips

- **Full-auto mode**: Codex works well in `--full-auto` mode for routine Marko tasks like generating module boilerplate. The `validate_module` MCP tool helps it self-check generated code.
- **Skills as tasks**: Marko skills in `resources/ai/skills/` map naturally to Codex task descriptions. Reference a skill by name in your prompt to load its context.
- **Sandboxing**: When running with network sandboxing, ensure `marko mcp:serve` is allowed through since it communicates over stdio.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
