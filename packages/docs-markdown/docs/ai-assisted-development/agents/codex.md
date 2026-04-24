---
title: Codex
description: Set up Marko's AI tooling with OpenAI Codex CLI — AGENTS.md guidelines and MCP tool registration.
---

[OpenAI Codex CLI](https://github.com/openai/codex) is OpenAI's agentic coding tool. `devai:install` configures it with an `AGENTS.md` project guidelines file and MCP server registration.

## Prerequisites

- Codex CLI installed: `npm install -g @openai/codex`
- Authenticated: `codex auth login`
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Codex detected produces the following files:

```
AGENTS.md                          # Project guidelines for Codex
.codex/mcp.json                    # MCP server registration (marko mcp:serve)
```

### AGENTS.md

The root `AGENTS.md` receives a merged section containing:

- Marko module conventions and project structure overview
- Available MCP tools and their descriptions
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`
- Skill instructions from `resources/ai/skills/`

If an `AGENTS.md` already exists, `devai:install` appends a clearly marked `## Marko` section.

### MCP registration

The `.codex/mcp.json` file registers `marko mcp:serve` as an MCP server. Codex calls this server to invoke tools like `search_docs` and `find_event_observers` during agentic tasks.

## Manual verification

1. Open a terminal in your project root.
2. Run `codex "What Marko MCP tools are available?"` — Codex should list the registered tools from `marko/mcp`.
3. Run `codex "Search docs for routing"` — the `search_docs` tool should return Marko documentation results.
4. Check that `.codex/mcp.json` references `marko mcp:serve`.

## Agent-specific tips

- **Full-auto mode**: Codex works well in `--full-auto` mode for routine Marko tasks like generating module boilerplate. The `validate_module` MCP tool helps it self-check generated code.
- **Skills as tasks**: Marko skills in `resources/ai/skills/` map naturally to Codex task descriptions. Reference a skill by name in your prompt to load its context.
- **Sandboxing**: When running with network sandboxing, ensure `marko mcp:serve` is allowed through since it communicates over stdio.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
