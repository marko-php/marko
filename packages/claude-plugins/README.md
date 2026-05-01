# marko/claude-plugins

A Claude Code plugin marketplace shipping three plugins (`marko-skills`, `marko-lsp`, `marko-mcp`) that provide AI-assisted Marko development. This package is not a Marko runtime module --- it is a content and asset container for Claude Code; it ships no PHP classes and is not loaded by the Marko module system.

## Installation

Users typically do not install this package directly. Running `marko devai:install --agents=claude-code` writes the marketplace registration into the project's `.claude/settings.json` and Claude Code prompts to install on first trust.

For manual installation inside Claude Code:

```shell
/plugin marketplace add marko-php/marko
/plugin install marko-skills@marko marko-lsp@marko marko-mcp@marko
```

## What's Included

| Plugin | Description |
|---|---|
| `marko-skills` | Scaffolding skills for generating modules, commands, services, and other Marko artefacts |
| `marko-lsp` | Marko-aware language server providing completions, diagnostics, and hover docs in editors |
| `marko-mcp` | Codebase introspection MCP server exposing module graph, routes, and config to AI agents |

## Documentation

Full setup, plugin reference, and configuration options: [marko/claude-plugins](https://marko.build/docs/ai-assisted-development/)
