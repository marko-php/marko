---
title: AI-assisted Development
description: Build Marko apps with first-class AI agent support via devai, MCP tools, and LSP completions.
---

Marko ships first-class AI agent support out of the box. The `marko/devai` installer wires up MCP tools, LSP features, and per-agent guidelines for Claude Code, Codex, Cursor, GitHub Copilot, Gemini CLI, and JetBrains Junie.

## The trio

Three packages work together to give every supported agent a complete picture of your Marko project:

- **`marko/devai`** — Installer and orchestrator. Run `marko devai:install` once to detect every agent you have configured and write the correct integration files automatically.
- **`marko/mcp`** — An MCP (Model Context Protocol) server providing structured tools the agent can call: `search_docs`, `find_event_observers`, `validate_module`, `query_database`, and more. Started via `marko mcp:serve`.
- **`marko/lsp`** — A Language Server Protocol implementation for Marko-specific completions: config keys, template names, translation keys, attribute parameters, and event names. Started via `marko lsp:serve`.

Together, these three packages give agents accurate, project-specific context without requiring any manual prompt engineering.

## Quick start

```bash
composer require --dev marko/devai
marko devai:install
```

`devai:install` inspects your environment, detects which agents are present, and writes the necessary configuration files for each one. See the [Installation guide](./installation/) for the full walkthrough.

## What each package provides

### marko/devai

- Single `devai:install` command that auto-detects Claude Code, Codex, Cursor, Copilot, Gemini CLI, and Junie
- Writes agent-specific configuration files (`CLAUDE.md`, `.cursorrules`, `.copilot/`, etc.)
- Registers the MCP server and LSP server with each agent that supports them
- Embeds per-package guidelines and skills from `resources/ai/` directories across your installed packages

### marko/mcp

Provides callable tools over the Model Context Protocol so agents can query your project at runtime:

| Tool | Description |
|---|---|
| `search_docs` | Full-text or semantic search across Marko documentation |
| `find_event_observers` | List observers registered for a given event |
| `validate_module` | Check a module for structural or dependency errors |
| `query_database` | Run a read-only query and return results as structured data |

See the [`marko/mcp` README](https://github.com/markshust/marko/tree/develop/packages/mcp) for the full tool list.

### marko/lsp

Provides IDE-style completions and diagnostics inside any editor with LSP support:

- Config key completions (dot-notation, with type hints)
- Template name completions for `marko/view` directives
- Translation key completions from your `resources/lang/` files
- Attribute parameter completions for Marko PHP attributes
- Event name completions when registering observers

See the [`marko/lsp` README](https://github.com/markshust/marko/tree/develop/packages/lsp) for the full feature list.

## Where to next

- [Installation guide](./installation/) — detailed setup steps
- [Per-agent setup](./agents/claude-code/) — Claude Code, Codex, Cursor, Copilot, Gemini CLI, Junie
- [Docs driver comparison](./docs-drivers/) — pick lexical (fts) or semantic (vec) search
- [Verification checklist](./verification-checklist/) — confirm everything works end-to-end
- [Contributing](./contributing/) — package authors: add your own skills and guidelines
- [Troubleshooting](./troubleshooting/) — common issues and fixes
- [Architecture](./architecture/) — how codeindexer, MCP, and LSP fit together
