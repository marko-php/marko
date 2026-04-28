---
title: Installation
description: Install marko/devai and run devai:install to wire up MCP tools, LSP, and per-agent guidelines.
---

This page covers the full installation flow for Marko's AI-assisted development tooling: the `marko/devai` package and the `devai:install` command that configures each detected agent automatically.

## Requirements

- PHP 8.5+
- Composer 2.x
- An existing Marko project (see [Getting Started](../getting-started/installation/))
- At least one supported AI agent installed (Claude Code, Codex, Cursor, Copilot, Gemini CLI, or Junie)

## Step 1: Require the package

Install `marko/devai` as a dev dependency:

```bash
composer require --dev marko/devai
```

This also pulls in `marko/mcp` and `marko/lsp` as dependencies, so you do not need to require them separately.

## Step 2: Run the installer

```bash
marko devai:install
```

The installer does the following in order:

1. **Detects agents** — Scans your environment and project directory for known agent configuration files and binaries.
2. **Indexes the codebase** — Runs `marko codeindexer:index` to build the search index used by `marko/mcp` and `marko/lsp`.
3. **Writes agent files** — For each detected agent, writes the appropriate configuration files (guidelines, MCP registration, LSP registration).
4. **Merges package skills** — Reads every installed package's `resources/ai/guidelines.md` and `resources/ai/skills/` directory and merges the content into the agent guidelines.

### Non-destructive by default

`devai:install` never overwrites existing files without confirmation. If an agent file already exists, you will be prompted to merge, overwrite, or skip.

## Step 3: Start the servers (optional)

Most agents that support MCP and LSP will start the servers automatically via the registered commands. If you need to start them manually for debugging:

```bash
# MCP server (stdio transport)
marko mcp:serve

# LSP server (stdio transport)
marko lsp:serve
```

## Step 4: Verify

Run through the [Verification checklist](./verification-checklist/) to confirm every piece is working end-to-end.

## Updating

When you add new Marko packages, re-run the installer to pick up any new skills and guidelines they ship:

```bash
marko devai:install
```

## Per-agent setup

Each agent has its own page with agent-specific details:

- [Claude Code](./agents/claude-code/)
- [Codex](./agents/codex/)
- [Cursor](./agents/cursor/)
- [GitHub Copilot](./agents/copilot/)
- [Gemini CLI](./agents/gemini-cli/)
- [JetBrains Junie](./agents/junie/)

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
