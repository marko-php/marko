---
title: JetBrains Junie
description: Set up Marko's AI tooling with JetBrains Junie — guidelines, MCP tools, and LSP completions inside PhpStorm and IntelliJ.
---

[JetBrains Junie](https://www.jetbrains.com/junie/) is JetBrains' agentic AI assistant built into PhpStorm, IntelliJ IDEA, and other JetBrains IDEs. `devai:install` configures it with a project guidelines file, MCP server registration, and LSP completions via the JetBrains Language Server Protocol support.

## Prerequisites

- PhpStorm 2024.1+ or IntelliJ IDEA 2024.1+ with Junie enabled
- Junie plugin activated in your IDE
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Junie detected produces the following files:

```
.junie/guidelines.md               # Project guidelines read by Junie on each session
.junie/mcp.json                    # MCP server registration (marko mcp:serve)
.idea/marko-lsp.xml                # LSP server registration (marko lsp:serve)
```

### guidelines.md

The `.junie/guidelines.md` file is Junie's primary source of project context. The installer writes merged Marko guidelines:

- Module structure and naming conventions
- Available MCP tools and their descriptions
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`
- Skill instructions from `resources/ai/skills/`

If `guidelines.md` already exists, `devai:install` appends a `## Marko` section.

### MCP registration

The `.junie/mcp.json` file registers `marko mcp:serve` as an MCP server. Junie calls it via stdio transport to invoke tools like `search_docs` and `validate_module` during agentic tasks.

### LSP registration

The `.idea/marko-lsp.xml` file configures JetBrains' built-in LSP client to connect to `marko lsp:serve`. This enables:

- Config key completions in PHP and YAML files
- Template name completions in PHP and Twig files
- Translation key completions
- Event name completions when writing observers

## Manual verification

1. Open your project in PhpStorm with Junie enabled.
2. Open the Junie panel and ask: `What Marko MCP tools are available?`
3. Ask: `Search Marko docs for "authentication"` — `search_docs` should return results.
4. Open a PHP file, type `config('` — Marko config key completions should appear via LSP.
5. Check **Settings > Languages & Frameworks > Language Servers** to confirm `marko lsp:serve` is listed and connected.

## Agent-specific tips

- **Agentic tasks**: Junie excels at longer, multi-step tasks. Use it with `validate_module` to run a self-check after generating boilerplate code.
- **Guidelines vs. prompts**: The `.junie/guidelines.md` file is always in context. Keep it focused on conventions. Put step-by-step skill instructions in `resources/ai/skills/` so they are only loaded when relevant.
- **LSP diagnostics**: PhpStorm's LSP integration shows Marko-specific warnings (e.g., missing observer `execute` return type) as you type, even without asking Junie anything.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
