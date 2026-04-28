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

The codeindexer is the shared data layer. It reads:

- Your project's PHP source files (`app/`, `src/`)
- Every installed package's `resources/ai/guidelines.md`
- Marko's own documentation (pulled from `marko/docs-markdown`)
- Module metadata (`module.php`, `composer.json` files)

It writes a SQLite database (default: `storage/framework/codeindex.sqlite`) containing:

- A full-text search index (FTS5) used by the `docs-fts` driver
- Optionally, vector embeddings used by the `docs-vec` driver
- Structured tables for observers, routes, config keys, and template names

Run the indexer explicitly:

```bash
marko codeindexer:index
```

Or configure it to re-index on file changes during development:

```bash
marko codeindexer:watch
```

See the [`marko/codeindexer` README](https://github.com/markshust/marko/tree/develop/packages/codeindexer) for full configuration.

## marko/mcp

The MCP server exposes codeindex data to AI agents through the [Model Context Protocol](https://modelcontextprotocol.io/). It runs as a long-lived process communicating over stdio (or SSE for remote agents).

**How it reads the index:** Every MCP tool call opens a read-only connection to the SQLite database and queries the relevant tables. No writes happen through MCP.

**Available tools:**

| Tool | Index table | Description |
|---|---|---|
| `search_docs` | `docs_fts` or `docs_vec` | Search Marko docs and package guidelines |
| `find_event_observers` | `observers` | List observers registered for an event |
| `validate_module` | `modules`, `bindings` | Check a module for structural errors |
| `query_database` | n/a (live DB) | Run a read-only query against the app database |

Start the MCP server:

```bash
marko mcp:serve
```

See the [`marko/mcp` README](https://github.com/markshust/marko/tree/develop/packages/mcp) for transport options and tool reference.

## marko/lsp

The LSP server exposes codeindex data to editors through the [Language Server Protocol](https://microsoft.github.io/language-server-protocol/). It runs as a long-lived process communicating over stdio.

**How it reads the index:** Like MCP, the LSP server queries the SQLite database read-only. It also watches for database changes via SQLite's `wal` journal mode and sends `textDocument/publishDiagnostics` notifications when the index updates.

**Features powered by the index:**

| LSP feature | Index source |
|---|---|
| Config key completions | `config_keys` table |
| Template name completions | `templates` table |
| Translation key completions | `translation_keys` table |
| Event name completions | `events` table |
| Observer diagnostics | `observers` + `events` tables |

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
3. MCP server queries `docs_fts` (or `docs_vec`) in the SQLite index
4. Index returns ranked chunks from Marko docs and package guidelines
5. MCP returns chunks to agent
6. Agent synthesizes an answer using the retrieved content

## Data flow for a config key completion

1. Developer types `config('` in a PHP file
2. Editor sends `textDocument/completion` to the LSP server
3. LSP server queries `config_keys` in the SQLite index
4. Returns a list of completion items with types and descriptions
5. Editor renders the completion dropdown

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
- [`marko/lsp`](https://github.com/markshust/marko/tree/develop/packages/lsp)
- [`marko/codeindexer`](https://github.com/markshust/marko/tree/develop/packages/codeindexer)
