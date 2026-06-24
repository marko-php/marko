---
title: Troubleshooting
description: Fix common install failures, MCP/LSP server problems, and agent registration problems for Marko's AI tooling.
---

This page covers the most common issues encountered when setting up `marko/devai`, `marko/mcp`, and `marko/lsp`.

## Installation failures

### "marko command not found" after composer require

The `marko` binary is published to `vendor/bin/`. If you don't already have it on your `PATH` from a global install, add the project's `vendor/bin/` to your shell:

```bash
export PATH="vendor/bin:$PATH"
```

Or invoke the binary by its project-local path: `./vendor/bin/marko ...`.

### devai:install exits with "No agents detected"

The installer detects agents by looking for known configuration files and binaries. If no agents are found:

1. Confirm the agent's CLI is on your `PATH` (e.g., `claude`, `codex`, `gemini` for the agents that gate detection on a binary).
2. Bypass detection and force a specific agent set with the `--agents` flag (comma-separated, no space):

```bash
marko devai:install --agents=claude-code,codex
```

Supported agent identifiers: `claude-code`, `codex`, `cursor`, `copilot`, `gemini-cli`, `junie`.

### Permission denied writing agent files

If the installer cannot write `CLAUDE.md` or other files, check directory permissions:

```bash
ls -la . | head -5
```

If running inside a container or mounted volume, ensure the working directory is writable by the PHP process.

## MCP server problems

### MCP tools not available on the first session after install

On a brand-new project, `mcp:serve` boots the framework for the first time during the agent's session-init handshake. If the discovery cache and code index have not been compiled yet, this cold-boot can exceed the agent's probe timeout and the MCP server appears unavailable.

`devai:install` now pre-warms these caches automatically (runs `marko discovery:cache` and `marko indexer:rebuild`) so the first session starts on the fast path. If you still see missing tools on first launch, run the warm-up manually and restart the agent:

```bash
marko discovery:cache
marko indexer:rebuild
```

Then reload the MCP connection (e.g., `/mcp` in Claude Code) or restart the agent session.

### Agent reports "MCP server failed to start"

1. Confirm `marko mcp:serve` runs without error:

```bash
marko mcp:serve
# Should block waiting for stdin — press Ctrl+C to exit
```

2. Check the agent's MCP configuration file references the correct command:

```json
{
  "command": "marko",
  "args": ["mcp:serve"]
}
```

3. Ensure the `marko` binary is on the `PATH` the agent uses. Some agents (e.g., Claude Code) launch their MCP servers in a restricted environment. If `marko` is not on the agent's `PATH`, point the registration at the absolute path of the binary:

```json
{
  "command": "/absolute/path/to/marko",
  "args": ["mcp:serve"]
}
```

### "Tool not found" when calling search_docs

The `search_docs` tool is only registered when a `DocsSearchInterface` binding is present. This requires installing a docs driver package such as `marko/docs-fts`. If no driver is installed, the tool will not appear in the MCP tool list regardless of the index state.

If the tool is listed but returns no results, the index may be stale. Trigger a rebuild:

```bash
marko indexer:rebuild
```

The `IndexCache` also rebuilds automatically on next read if any tracked source file is newer than the cache.

### "Tool not found" when calling query_database

The `query_database` tool is only registered when `marko/database` is bound in the container. Install the database package and ensure it is configured before expecting this tool to appear.

## LSP problems

### No completions appearing in the editor

1. Confirm `marko lsp:serve` runs:

```bash
marko lsp:serve
# Should block waiting for stdin — press Ctrl+C
```

2. Check the editor's LSP configuration points to `marko lsp:serve`.

3. In VS Code: open the Output panel, select "Marko Language Server" from the dropdown — connection errors appear here.

4. In PhpStorm: check **Settings > Languages & Frameworks > Language Servers** for connection status.

### Completions appear but are stale or incorrect

The MCP and LSP servers lazy-load `.marko/index.cache` and rebuild it whenever a watched source file is newer than the cache, so completions usually self-heal. If they don't, force a clean rebuild:

```bash
marko indexer:rebuild
```

## Agent registration problems

### CLAUDE.md / AGENTS.md not updated after re-running devai:install

A second `marko devai:install` run is a no-op once `.marko/devai.json` exists — the orchestrator prints "Prior install detected at .marko/devai.json. Use `marko devai:update` to update, or pass --force to re-run." To force a refresh:

```bash
marko devai:install --force
```

This regenerates the Marko section in every agent guidelines file based on the current set of installed packages.

### Guidelines from a newly installed package are not appearing

After adding a new package, re-run the installer:

```bash
composer require my-vendor/my-package
marko devai:install
```

The installer reads `resources/ai/guidelines.md` from every package in `vendor/` each time it runs.

## Getting more help

- [Verification checklist](./verification-checklist/) — step-by-step smoke test to isolate where the problem is
- [Architecture](./architecture/) — understand how the components connect
- [GitHub Issues](https://github.com/markshust/marko/issues) — search for known issues or file a new one
