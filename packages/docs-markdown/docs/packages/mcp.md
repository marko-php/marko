---
title: marko/mcp
description: MCP server exposing Marko codebase introspection to AI coding agents.
---

Model Context Protocol (MCP) server that exposes Marko codebase introspection to AI coding agents (Claude Code, Cursor, Codex, and any MCP client). It speaks JSON-RPC over stdio (`marko mcp:serve`) and answers structured questions about the project — modules, routes, observers, plugins, config, templates — so an agent can reason about a Marko app without grepping.

Like [`marko/lsp`](/docs/packages/lsp/), it builds on [`marko/codeindexer`](/docs/packages/codeindexer/): tools read the cached symbol index rather than re-parsing. (Installing `marko/mcp` pulls codeindexer in automatically.)

## Installation

```bash
composer require marko/mcp
```

## Usage

```bash
marko mcp:serve
```

Register it with your agent, e.g. for Claude Code:

```bash
claude mcp add marko-mcp -- marko mcp:serve
```

## Tools

Always available (backed by the code index):

| Tool | Purpose |
|------|---------|
| `list_modules`, `list_commands`, `list_routes` | Enumerate the module graph |
| `find_event_observers` | Observers listening to an event |
| `find_plugins_targeting` | Plugins intercepting a class |
| `resolve_preference` | Resolve an interface/class to its bound implementation or `#[Preference]` |
| `resolve_template` | Resolve a `module::template` to an absolute path |
| `get_config_schema`, `check_config_key` | Inspect config keys |
| `validate_module` | Validate a module's structure |
| `app_info` | PHP / Marko / DB engine / installed package versions |

Runtime tools (degrade gracefully when their dependency isn't present):

| Tool | Requires | Notes |
|------|----------|-------|
| `read_log_entries` | a log directory | Reads recent log entries; filter by `level` (use `level: error, limit: 1` for the most recent error) |
| `run_console_command` | — | Runs a `marko` CLI command and captures output |
| `query_database` | a `marko/database` driver | Read-only by default; registered only when a DB connection is available |
| `search_docs` | a docs driver (`marko/docs-fts` / `marko/docs-vec`) | Registered only when a `DocsSearchInterface` is bound |

> There is intentionally **no `last_error` tool** and no global error-capture plugin. "Most recent error" is `read_log_entries(level: 'error', limit: 1)` — one tool, no production-time side effects.

## Related Packages

- [`marko/codeindexer`](/docs/packages/codeindexer/) — the cached index the tools read
- [`marko/lsp`](/docs/packages/lsp/) — the editor-facing peer that reads the same index
- [`marko/docs-fts`](/docs/packages/docs-fts/) / [`marko/docs-vec`](/docs/packages/docs-vec/) — enable `search_docs`
