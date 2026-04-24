---
title: GitHub Copilot
description: Set up Marko's AI tooling with GitHub Copilot — workspace instructions, MCP tools, and LSP completions.
---

[GitHub Copilot](https://github.com/features/copilot) is available in VS Code, JetBrains IDEs, and the terminal via the `gh copilot` extension. `devai:install` configures it with workspace instructions, MCP server registration, and LSP completions.

## Prerequisites

- GitHub Copilot subscription active on your GitHub account
- VS Code with the [GitHub Copilot extension](https://marketplace.visualstudio.com/items?itemName=GitHub.copilot), or a JetBrains IDE with the Copilot plugin
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Copilot detected produces the following files:

```
.github/copilot-instructions.md    # Workspace-level guidelines for Copilot Chat
.vscode/mcp.json                   # MCP server registration (marko mcp:serve)
.vscode/settings.json              # LSP server registration (marko lsp:serve)
```

### copilot-instructions.md

The `.github/copilot-instructions.md` file is read by Copilot Chat for every workspace session. The installer writes merged Marko guidelines:

- Module structure and naming conventions
- Available MCP tools
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`
- Skill instructions from `resources/ai/skills/`

If the file already exists, `devai:install` appends a `## Marko` section.

### MCP registration

The `.vscode/mcp.json` file registers `marko mcp:serve` as an MCP server. Copilot Chat's agent mode can call tools like `search_docs` and `find_event_observers` when answering questions about your project.

### LSP registration

The `.vscode/settings.json` file adds `marko lsp:serve` as a language server via the `"languageServerProtocol.servers"` key, enabling config key and template name completions.

## Manual verification

1. Open your project in VS Code with Copilot enabled.
2. Open Copilot Chat and ask: `What Marko MCP tools are available?`
3. In agent mode, ask: `Search Marko docs for "dependency injection"` — `search_docs` should run and return results.
4. Open a PHP file, type `config('` — LSP config key completions should appear.
5. Check that `.github/copilot-instructions.md` contains the `## Marko` section.

## Agent-specific tips

- **Agent mode**: MCP tool calls require Copilot Chat to be in agent mode (`#agent`). Switch to it by clicking the agent icon in the chat panel.
- **JetBrains**: For JetBrains IDEs, the installer writes a `.copilot/instructions.md` file in the project root instead of `.github/copilot-instructions.md`. MCP is configured separately — see the JetBrains Copilot plugin settings.
- **`gh copilot`**: The terminal extension does not yet support MCP or LSP. Guidelines from `copilot-instructions.md` are not loaded in the terminal context.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
