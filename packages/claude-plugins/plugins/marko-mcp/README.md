# marko-mcp

Registers the Marko MCP server with Claude Code so the `marko mcp:serve` command is available as a tool provider inside Claude Code sessions.

## What this plugin registers

A single MCP server (`marko-mcp`) that delegates to `marko mcp:serve`. The server is invoked via a POSIX shim at `bin/marko-mcp` that locates the `marko` binary — first checking `./vendor/bin/marko` (project-local Composer install), then falling back to a globally-installed `marko` on PATH.

If no `marko` binary is found, the shim exits with a helpful error directing you to run `composer require marko/devai`.

## Install

Add the Marko marketplace once, then install the plugin:

```
/plugin marketplace add marko-php/marko
/plugin install marko-mcp@marko
```

## Verify

After installation, confirm the MCP server is registered:

```
claude mcp list
```

You should see `marko-mcp` in the list.

## Requirements

- `marko/devai` must be installed in the project (`composer require marko/devai`) or `marko` must be available globally on PATH.

## Docs

https://marko.dev
