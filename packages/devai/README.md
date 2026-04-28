# marko/devai

AI-assisted development installer for Marko — wires MCP, LSP, and per-agent configs for Claude Code, Codex, Cursor, Copilot, Gemini CLI, and Junie.

## Overview

`marko/devai` is the one-stop installer that provisions your project for AI-assisted development. It installs agent-specific configuration files (`.mcp.json`, `.cursorrules`, `.github/copilot-instructions.md`, etc.), starts the MCP and LSP servers, and keeps everything up to date as new agents or Marko capabilities are added. Run `marko devai:install` once per project; run `marko devai:update` to pull in new agent configs.

## Installation

```bash
composer require marko/devai
```

## Usage

Provision the project for all supported AI agents:

```bash
marko devai:install
```

Update agent configs to the latest version:

```bash
marko devai:update
```

## Supported Agents

| Agent | Config installed |
|-------|-----------------|
| Claude Code | `.mcp.json`, `CLAUDE.md` |
| Codex | `codex.md` |
| Cursor | `.cursorrules`, `.cursor/mcp.json` |
| GitHub Copilot | `.github/copilot-instructions.md` |
| Gemini CLI | `GEMINI.md` |
| Junie | `.junie/guidelines.md` |

All agents are configured to use the `marko/mcp` server for codebase introspection and `marko/lsp` for semantic completions.

## Documentation

Full agent setup and configuration options: [marko/devai](https://marko.build/docs/ai-assisted-development/devai/)
