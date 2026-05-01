# marko/mcp

MCP server exposing Marko codebase introspection to Claude Code, Codex, Cursor, and other AI agents.

## Installation

```bash
composer require marko/mcp
```

## Quick Example

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

## Documentation

Full tool reference, runtime adapters, and agent configuration: [marko/mcp](https://marko.build/docs/ai-assisted-development/mcp/)
