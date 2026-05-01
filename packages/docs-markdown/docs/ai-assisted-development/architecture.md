---
title: Architecture
description: How marko/codeindexer feeds both the MCP server and the LSP server, and how all three connect to AI agents.
---

This page describes how `marko/codeindexer`, `marko/mcp`, and `marko/lsp` fit together, and how `marko/devai` orchestrates the whole system.

## Component overview

```
┌──────────────────────────────────────────────────────────────────┐
│                        AI Agent                                   │
│  (Claude Code / Codex / Cursor / Copilot / Gemini CLI / Junie)   │
└───────────┬──────────────────────────┬───────────────────────────┘
            │ MCP (stdio/SSE)          │ LSP (stdio)
            ▼                          ▼
┌───────────────────┐      ┌───────────────────────┐
│   marko/mcp       │      │   marko/lsp            │
│   mcp:serve       │      │   lsp:serve            │
└─────────┬─────────┘      └──────────┬────────────┘
          │                           │
          └──────────┬────────────────┘
                     │ reads
                     ▼
          ┌───────────────────────┐
          │  marko/codeindexer    │
          │  SQLite index         │
          │  (FTS5 or vec)        │
          └──────────┬────────────┘
                     │ indexes
                     ▼
          ┌───────────────────────┐
          │  Project source       │
          │  + vendor resources/  │
          │  + Marko docs         │
          └───────────────────────┘
```

## marko/codeindexer

The codeindexer is the shared data layer. It walks every installed module and scans:

- PHP source files for attributes (observers, plugins, preferences, commands, routes)
- `config/` directories for config key definitions
- `resources/views/` for template entries
- `resources/translations/` for translation keys
- Module metadata (`module.php`, `composer.json` files)

It serializes the result to a file cache at `.marko/index.cache` in the project root.

**Lazy-load with auto-rebuild:** The `IndexCache` loads the cache from disk the first time any consumer (the MCP server, LSP server, etc.) reads from it. If the cache file is missing or stale (any tracked source file is newer than the cache), `IndexCache` automatically runs a full build before returning data. No explicit rebuild step is required in normal development.

To pre-warm or force a rebuild explicitly:

```bash
marko indexer:rebuild
```

See the [`marko/codeindexer` README](https://github.com/markshust/marko/tree/develop/packages/codeindexer) for full configuration.

## marko/mcp

The MCP server exposes codeindex data to AI agents through the [Model Context Protocol](https://modelcontextprotocol.io/). It runs as a long-lived process communicating over stdio.

**How it reads the index:** Every MCP tool call reads from the `IndexCache`. The cache is loaded lazily on first access and rebuilt automatically if stale. No writes happen through MCP.

**Always-registered tools (14):**

Ten tools are backed by `IndexCache`: `check_config_key`, `find_event_observers`, `find_plugins_targeting`, `get_config_schema`, `list_commands`, `list_modules`, `list_routes`, `resolve_preference`, `resolve_template`, and `validate_module`.

Four runtime tools are always registered: `app_info`, `last_error`, `read_log_entries`, and `run_console_command`.

**Conditional tools:**

- `query_database` — registered when `marko/database` is bound in the container
- `search_docs` — registered when a `DocsSearchInterface` binding is present (provided by `marko/docs-fts`, `marko/docs-vec`, or another docs driver package)

**Error capture:** The `PersistLastErrorPlugin` is auto-discovered via the `#[Plugin]` attribute and intercepts `ErrorHandlerInterface::handle()` calls. It writes the most recent throwable to `storage/last_error.json` so the `last_error` tool can return it to agents without any manual wiring.

Start the MCP server:

```bash
marko mcp:serve
```

See the [`marko/mcp` README](https://github.com/markshust/marko/tree/develop/packages/mcp) for the full tool reference.

## marko/lsp

The LSP server exposes codeindex data to editors through the [Language Server Protocol](https://microsoft.github.io/language-server-protocol/). It runs as a long-lived process communicating over stdio.

**How it reads the index:** Like MCP, the LSP server reads from the `IndexCache`, which lazy-loads and auto-rebuilds as needed.

**Wired LSP methods:**

`textDocument/didOpen`, `textDocument/didChange`, `textDocument/didClose`, `textDocument/completion`, `textDocument/definition`, `textDocument/hover`, `textDocument/diagnostic`, `textDocument/codeLens`

**Advertised capabilities:**

- `completionProvider` — trigger characters `"`, `'`, `:`, `.`
- `definitionProvider: true`
- `hoverProvider: true`
- `codeLensProvider`
- `diagnosticProvider`

Start the LSP server:

```bash
marko lsp:serve
```

See the [`marko/lsp` README](https://github.com/markshust/marko/tree/develop/packages/lsp) for editor setup and feature reference.

## marko/devai

`devai` is the orchestrator — it does not read the codeindex directly. Its job is to wire everything together at install time:

1. Detects which agents are present
2. Writes agent configuration files that point to `marko mcp:serve` and `marko lsp:serve`
3. Merges guidelines from `resources/ai/guidelines.md` files across all installed packages into the agent guidelines files
4. Registers skills from `resources/ai/skills/` so agents can load them on demand

After `devai:install` runs, the MCP and LSP servers start and stop on demand as the agent needs them. The codeindexer runs once up-front and again whenever you run it explicitly or via `codeindexer:watch`.

See the [`marko/devai` README](https://github.com/markshust/marko/tree/develop/packages/devai) for the full installer reference.

## Data flow for a search_docs call

1. Developer asks agent: "How does Marko handle events?"
2. Agent calls `search_docs` tool via MCP
3. MCP server delegates to the bound `DocsSearchInterface` driver (e.g., `docs-fts` or `docs-vec`)
4. Driver returns ranked chunks from Marko docs and package guidelines
5. MCP returns chunks to agent
6. Agent synthesizes an answer using the retrieved content

The `search_docs` tool is only registered when a `DocsSearchInterface` binding exists. If no docs driver is installed, the tool is unavailable.

## Data flow for a config key completion

1. Developer types `config('` in a PHP file
2. Editor sends `textDocument/completion` to the LSP server
3. LSP server reads config key entries from `IndexCache`
4. Returns a list of completion items with types and descriptions
5. Editor renders the completion dropdown

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
- [`marko/codeindexer`](https://github.com/markshust/marko/tree/develop/packages/codeindexer)
