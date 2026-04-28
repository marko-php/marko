# marko/mcp

MCP server exposing Marko codebase introspection to Claude Code, Codex, Cursor, and other AI agents.

## Overview

`marko/mcp` implements the Model Context Protocol, providing AI agents with structured, queryable access to your Marko project. Agents can list modules, resolve preferences, inspect config schemas, find observers and plugins, resolve templates, and search documentation — all without reading raw source files.

## Installation

```bash
composer require marko/mcp
```

## Usage

Start the MCP server (stdio transport, used by AI agent integrations):

```bash
marko mcp:serve
```

Configure your AI agent (e.g. Claude Code `.mcp.json`):

```json
{
  "mcpServers": {
    "marko": {
      "command": "marko",
      "args": ["mcp:serve"]
    }
  }
}
```

## MCP Tools

| Tool | Description |
|------|-------------|
| `list_modules` | List all installed Marko modules |
| `list_commands` | List all registered CLI commands |
| `list_routes` | List all routes with methods and handlers |
| `get_config_schema` | Get config keys and their types for a module |
| `check_config_key` | Validate a config key exists |
| `resolve_preference` | Resolve which class wins for an interface |
| `resolve_template` | Find the active template for a given handle |
| `find_event_observers` | Find all observers for a given event class |
| `find_plugins_targeting` | Find all plugins targeting a given class |
| `validate_module` | Validate a module's structure |
| `search_docs` | Search Marko documentation |

## Documentation

Full setup and agent configuration: [marko/mcp](https://marko.build/docs/ai-assisted-development/mcp/)
