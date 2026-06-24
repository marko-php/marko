---
title: MCP Tools Reference
description: Complete reference for all tools exposed by marko/mcp — what each tool does, what it returns, and when it is available.
---

`marko/mcp` exposes tools to AI agents via the [Model Context Protocol](https://modelcontextprotocol.io/). Thirteen tools are always registered when the MCP server starts. Two additional tools are registered conditionally depending on which packages are installed.

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

These three tools are always registered and do not depend on the index.

| Tool | Description |
|---|---|
| `app_info` | Return the application name and the versions of all installed Marko packages (reads `composer.json` and `vendor/composer/installed.json`) |
| `read_log_entries` | Read recent entries from the application log files in `storage/logs/`, filterable by `level` and `limit`. To fetch the most recent error, call `read_log_entries(level: 'error', limit: 1)` |
| `run_console_command` | Run a Marko console command and return its output as a string |

## Conditional tools

### query_database

Registered when `marko/database` is bound in the container. Allows agents to run read-only SQL queries against the application database and receive results as structured data.

If `marko/database` is not installed, this tool does not appear in the MCP tool list.

### search_docs

Registered when a `DocsSearchInterface` binding is present in the container. That binding comes from a docs search driver — `marko/docs-fts` (SQLite FTS5 full-text search), or any other package that binds `DocsSearchInterface`.

When you run `marko devai:install` in an interactive terminal and no docs driver is installed, it prompts:

```
No docs search driver installed. Install marko/docs-fts to enable search_docs? [Y/n]
```

Answering yes runs `composer require --dev marko/docs-fts` and then builds the index automatically. In non-interactive mode, CI, or when `--no-interaction` is passed, the prompt is skipped and a hint is printed instead — the install never blocks.

To install and build manually:

```bash
composer require --dev marko/docs-fts
marko docs-fts:build
```

`marko devai:install` builds the index for you when a driver is already installed. If no docs driver is installed and the prompt is declined (or skipped), `search_docs` does not appear in the MCP tool list — the install still completes and logs how to add one.

## Fetching the most recent error

There is no dedicated "last error" tool. Agents fetch the most recent error through the log reader: `read_log_entries(level: 'error', limit: 1)`. This works against any log driver that implements `LogReaderInterface` (the default `FileLogReader` parses `storage/logs/`), so it keeps working when the log backend changes.

## Package READMEs

- [`marko/mcp`](https://github.com/markshust/marko/tree/develop/packages/mcp)
