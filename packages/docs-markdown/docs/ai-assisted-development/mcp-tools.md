---
title: MCP Tools Reference
description: Complete reference for all tools exposed by marko/mcp — what each tool does, what it returns, and when it is available.
---

`marko/mcp` exposes tools to AI agents via the [Model Context Protocol](https://modelcontextprotocol.io/). Fourteen tools are always registered when the MCP server starts. Two additional tools are registered conditionally depending on which packages are installed.

## Always-registered tools

### IndexCache-backed tools

These ten tools read from the `IndexCache`. The cache is loaded lazily on first access and rebuilt automatically if stale.

| Tool | Description |
|---|---|
| `check_config_key` | Check whether a given dot-notation config key exists in the project index |
| `find_event_observers` | Return all observers registered for a given event class |
| `find_plugins_targeting` | Return all plugins targeting a given class |
| `get_config_schema` | Return the schema definition for a config namespace |
| `list_commands` | List all console commands registered across installed modules |
| `list_modules` | List all installed Marko modules with their paths and metadata |
| `list_routes` | List all routes registered across installed modules |
| `resolve_preference` | Return the concrete class bound to a given interface |
| `resolve_template` | Return the resolved file path for a given template name |
| `validate_module` | Check a module for structural errors (missing bindings, malformed attributes, etc.) |

### Runtime tools

These four tools are always registered and do not depend on the index.

| Tool | Description |
|---|---|
| `app_info` | Return the application name and the versions of all installed Marko packages (reads `composer.json` and `vendor/composer/installed.json`) |
| `last_error` | Return the most recent error captured by the application error handler, including message, file, line, trace, and timestamp (reads `storage/last_error.json`) |
| `read_log_entries` | Read recent entries from the application log files in `storage/logs/` |
| `run_console_command` | Run a Marko console command and return its output as a string |

## Conditional tools

### query_database

Registered when `marko/database` is bound in the container. Allows agents to run read-only SQL queries against the application database and receive results as structured data.

If `marko/database` is not installed, this tool does not appear in the MCP tool list.

### search_docs

Registered when a `DocsSearchInterface` binding is present in the container. This binding is provided by a docs driver package:

- `marko/docs-fts` — Full-text search via SQLite FTS5
- `marko/docs-vec` — Semantic vector search via ONNX embeddings
- Any package that binds `DocsSearchInterface`

If no docs driver is installed, this tool does not appear in the MCP tool list. See the [Docs driver comparison](./docs-drivers/) for how to choose and install a driver.

## Error capture

The `last_error` tool reads from `storage/last_error.json`. This file is populated automatically by the `PersistLastErrorPlugin`, which is auto-discovered via the `#[Plugin]` attribute and intercepts every call to `ErrorHandlerInterface::handle()`. No manual wiring is required — any error that passes through the application error handler is captured.

## Package READMEs

- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
