---
title: AI-assisted Development
description: Build Marko apps with first-class AI agent support via devai, MCP tools, and LSP completions.
---

Marko ships first-class AI agent support out of the box. The `marko/devai` installer wires up MCP tools, LSP features, and per-agent guidelines for Claude Code, Codex, Cursor, GitHub Copilot, Gemini CLI, and JetBrains Junie.

## The trio

Three packages work together to give every supported agent a complete picture of your Marko project:

- **`marko/devai`** — Installer and orchestrator. Run `marko devai:install` once to detect every agent you have configured and write the correct integration files automatically.
- **`marko/mcp`** — An MCP (Model Context Protocol) server providing 14 always-registered tools plus conditional `query_database` and `search_docs` tools. Started via `marko mcp:serve`.
- **`marko/lsp`** — A Language Server Protocol implementation providing completions, hover, go-to-definition, diagnostics, and code lens for Marko-specific symbols. Started via `marko lsp:serve`.

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
- Writes agent-specific configuration files (`CLAUDE.md`, `.cursor/rules/marko.mdc`, `.github/copilot-instructions.md`, `GEMINI.md`, `junie/guidelines.md`, etc.)
- Registers the MCP server with each agent that supports it (via config file or CLI command depending on the agent)
- Distributes per-package guidelines and skills from `resources/ai/` directories across your installed packages

### marko/mcp

Provides callable tools over the Model Context Protocol so agents can query your project at runtime. Fourteen tools are always available, with two registered conditionally:

| Tool | Notes |
|---|---|
| `check_config_key` | Verify a config key exists in the index |
| `find_event_observers` | List observers registered for a given event |
| `find_plugins_targeting` | List plugins targeting a given class |
| `get_config_schema` | Return the schema for a config namespace |
| `list_commands` | List all registered console commands |
| `list_modules` | List all installed Marko modules |
| `list_routes` | List all registered routes |
| `resolve_preference` | Resolve the concrete class bound to an interface |
| `resolve_template` | Resolve the file path for a template name |
| `validate_module` | Check a module for structural errors |
| `app_info` | Return application name and installed package versions |
| `last_error` | Return the most recent error captured by the error handler |
| `read_log_entries` | Read recent entries from the application log |
| `run_console_command` | Run a console command and return its output |
| `query_database` | Conditional — requires `marko/database` |
| `search_docs` | Conditional — requires a `DocsSearchInterface` binding |

See the [MCP tools reference](./mcp-tools/) for the full list with return types and parameters.

### marko/lsp

Provides IDE-style completions, hover, go-to-definition, diagnostics, and code lens inside any editor with LSP support:

- Config key completions, hover documentation, and go-to-definition
- Template name completions and go-to-definition
- Translation key completions and go-to-definition
- Attribute parameter completions
- Inline diagnostics for invalid config keys, templates, and translation keys

See the [`marko/lsp` README](https://github.com/markshust/marko/tree/develop/packages/lsp) for the full feature list.

## Where to next

- [Installation guide](./installation/) — detailed setup steps
- [Per-agent setup](./agents/claude-code/) — Claude Code, Codex, Cursor, Copilot, Gemini CLI, Junie
- [Docs driver comparison](./docs-drivers/) — pick lexical (fts) or semantic (vec) search
- [Verification checklist](./verification-checklist/) — confirm everything works end-to-end
- [Contributing](./contributing/) — package authors: add your own skills and guidelines
- [Troubleshooting](./troubleshooting/) — common issues and fixes
- [Architecture](./architecture/) — how codeindexer, MCP, and LSP fit together
