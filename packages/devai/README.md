# marko/devai

AI-assisted development installer for Marko — wires MCP, LSP, and per-agent configs for Claude Code, Codex, Cursor, Copilot, Gemini CLI, and Junie.

## Installation

```bash
composer require marko/devai
```

## Quick Example

```bash
marko devai:install
```

Detects every supported agent present in your environment and writes the correct configuration files for each one in a single pass.

For Claude Code, `devai:install` also auto-installs `intelephense` globally via npm if it is not already on `PATH`. To skip that step:

```bash
marko devai:install --skip-lsp-deps
```

When `--skip-lsp-deps` is passed, settings are still written correctly; only the intelephense npm install is omitted. A warning is printed reminding you to install it manually before general PHP LSP diagnostics will work.

## Documentation

Full agent setup, supported agents, and configuration options: [marko/devai](https://marko.build/docs/ai-assisted-development/devai/)
