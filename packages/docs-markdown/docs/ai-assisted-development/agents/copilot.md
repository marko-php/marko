---
title: GitHub Copilot
description: Set up Marko's AI tooling with GitHub Copilot — workspace instructions and MCP tools.
---

[GitHub Copilot](https://github.com/features/copilot) is available in VS Code, JetBrains IDEs, and the terminal via the `gh copilot` extension. `devai:install` configures it with workspace instructions and MCP server registration.

## Prerequisites

- GitHub Copilot subscription active on your GitHub account
- VS Code with the [GitHub Copilot extension](https://marketplace.visualstudio.com/items?itemName=GitHub.copilot), or a JetBrains IDE with the Copilot plugin
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Copilot detected produces the following files:

```
.github/copilot-instructions.md    # Workspace-level guidelines for Copilot Chat
.vscode/mcp.json                   # MCP server registration (marko mcp:serve)
AGENTS.md                          # Shared guidelines file (written if not already present)
```

Copilot does not implement LSP registration and no skills are distributed for Copilot.

### copilot-instructions.md

The `.github/copilot-instructions.md` file is read by Copilot Chat for every workspace session. The installer writes merged Marko guidelines:

- Module structure and naming conventions
- Available MCP tools
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`

### MCP registration

The `.vscode/mcp.json` file registers `marko mcp:serve` as an MCP server using the `servers` key (not `mcpServers`). Each entry includes a `type` field for the transport. Copilot Chat's agent mode can call tools like `find_event_observers` and `validate_module` when answering questions about your project.

### AGENTS.md

If no `AGENTS.md` exists in the project root, `devai:install` creates one with the same guidelines content. If `AGENTS.md` already exists, it is left untouched.

## Manual verification

1. Open your project in VS Code with Copilot enabled.
2. Open Copilot Chat and ask: `What Marko MCP tools are available?`
3. In agent mode, ask: `Search Marko docs for "dependency injection"` — `search_docs` should run and return results if a docs driver is installed.
4. Check that `.github/copilot-instructions.md` exists and `.vscode/mcp.json` uses the `servers` key.

## Agent-specific tips

- **Agent mode**: MCP tool calls require Copilot Chat to be in agent mode (`#agent`). Switch to it by clicking the agent icon in the chat panel.
- **`gh copilot`**: The terminal extension does not yet support MCP. Guidelines from `copilot-instructions.md` are not loaded in the terminal context.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
