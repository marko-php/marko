---
title: marko/devai
description: One-command installer that wires Marko's MCP and LSP tooling into your AI coding agents.
---

`marko/devai` is the integrator for Marko's AI development tooling. A single `marko devai:install` writes per-agent guidelines, registers the [`marko/mcp`](/docs/packages/mcp/) server and [`marko/lsp`](/docs/packages/lsp/) language server, distributes Marko skills, and builds the docs search index — for whichever of the six supported agents you use: Claude Code, OpenAI Codex, Cursor, GitHub Copilot, Gemini CLI, and JetBrains Junie.

It is the one point at which "AI tooling is available in this project" becomes true. For the conceptual guide and per-agent walkthroughs, see [AI-assisted Development](/docs/ai-assisted-development/).

## Installation

```bash
composer require --dev marko/devai
```

To enable docs search (`search_docs`), also install a docs driver — `marko/docs-fts` is the recommended default:

```bash
composer require --dev marko/docs-fts
```

## Usage

```bash
# Detects installed agent CLIs and installs for them
marko devai:install

# Or target specific agents explicitly
marko devai:install --agents=claude-code,cursor

# Re-run after adding packages (reuses the prior agent selection)
marko devai:update
```

`devai:install` writes the marker file `.marko/devai.json` recording the selected agents and shipped skills. Re-running without `--force` is a no-op once a project is installed; use `marko devai:update` to refresh, or `--force` to re-run from scratch.

## Commands

| Command | Purpose |
|---------|---------|
| `devai:install` | Install AI tooling for the selected agents (guidelines, MCP/LSP registration, skills, docs index) |
| `devai:update` | Re-run the install using the previously recorded agent selection |

### Options for `devai:install`

| Option | Effect |
|--------|--------|
| `--agents=<a,b>` | Install for an explicit comma-separated agent list instead of auto-detecting |
| `--force` | Overwrite an existing install |
| `--update-gitignore` | Append devai's generated paths to `.gitignore` |
| `--skip-lsp-deps` | Skip installing the intelephense LSP dependency (Claude Code) |

## How agents are installed

Each supported agent implements a single `AgentInterface::install()` method. The orchestrator renders the shared install data once (guidelines, skills, MCP registration) and hands it to every selected agent through an `InstallationContext` — so adding a new agent means implementing one method, with no capability-flag plumbing.

## Dependencies

`marko/devai` requires:

- **[`marko/mcp`](/docs/packages/mcp/)** — the MCP server it registers with each agent, so agents can introspect the codebase (`search_docs`, `validate_module`, `find_event_observers`, …).
- **[`marko/lsp`](/docs/packages/lsp/)** — the language server it wires up for real-time, Marko-aware editor diagnostics.
- **[`marko/docs`](/docs/packages/docs/)** — the docs-search *contract*. devai depends on the interface, not a specific driver, so you choose between [`marko/docs-fts`](/docs/packages/docs-fts/) (recommended, lexical) and [`marko/docs-vec`](/docs/packages/docs-vec/) (semantic). Install exactly one; binding both raises a boot-time conflict. See the [docs driver comparison](/docs/ai-assisted-development/docs-drivers/).
- **`marko/claude-plugins`** — the skills/plugins marketplace devai distributes into each agent (e.g. `/marko-skills:create-module`).

`marko/cli` and `marko/core` are pulled in transitively as the command/runtime foundation.

## Related Packages

- [`marko/mcp`](/docs/packages/mcp/) — codebase introspection over MCP
- [`marko/lsp`](/docs/packages/lsp/) — Marko-aware language server
- [`marko/docs`](/docs/packages/docs/) — docs search contract
- [`marko/docs-fts`](/docs/packages/docs-fts/) / [`marko/docs-vec`](/docs/packages/docs-vec/) — docs search drivers
