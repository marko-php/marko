---
title: Cursor
description: Set up Marko's AI tooling with Cursor — .cursorrules guidelines, MCP tools, and LSP completions.
---

[Cursor](https://cursor.sh) is an AI-first code editor built on VS Code. `devai:install` configures it with a `.cursorrules` project file, MCP server registration, and LSP completions.

## Prerequisites

- Cursor installed: [cursor.sh/download](https://cursor.sh/download)
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Cursor detected produces the following files:

```
.cursorrules                       # Project guidelines and conventions
.cursor/mcp.json                   # MCP server registration (marko mcp:serve)
.cursor/settings.json              # LSP server registration (marko lsp:serve)
```

### .cursorrules

The `.cursorrules` file receives merged Marko guidelines:

- Module structure and naming conventions
- Available MCP tools and usage patterns
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`
- Skill instructions from `resources/ai/skills/`

If a `.cursorrules` file already exists, `devai:install` appends a `# Marko` section.

### MCP registration

The `.cursor/mcp.json` file registers `marko mcp:serve` as an MCP server. Cursor's AI can invoke tools like `search_docs` and `query_database` during chat and Composer sessions.

### LSP registration

The `.cursor/settings.json` file adds `marko lsp:serve` as a language server, enabling Cursor to show Marko-specific completions for config keys, templates, and translation strings.

## Manual verification

1. Open your project in Cursor.
2. Open the AI chat panel and ask: `What Marko MCP tools are available?`
3. Ask: `Search Marko docs for "events"` — `search_docs` should return results.
4. Open a PHP file, type `config('` — config key completions should appear.
5. Check **Settings > Extensions > Language Servers** to confirm `marko lsp:serve` is listed.

## Agent-specific tips

- **Composer sessions**: Use Cursor's Composer for multi-file Marko tasks. The `validate_module` tool lets it verify its own output before presenting it to you.
- **Cursor rules vs. `.cursorrules`**: Cursor 0.40+ supports per-directory `.cursorrules` files. Place module-specific guidelines in `app/{module}/.cursorrules` for tightly scoped context.
- **MCP in chat**: Type `@mcp` in the Cursor chat to surface available MCP tools. The Marko tools appear automatically once `.cursor/mcp.json` is in place.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
