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

The running MCP/LSP server re-checks staleness on every read for `app/` and `modules/` — changes there are visible on the next tool call with no manual rebuild needed. Vendor and Composer changes are not covered by the on-read check; run `marko indexer:rebuild` after modifying `vendor/` or `composer.json`.

### "Tool not found" when calling query_database

The `query_database` tool is only registered when `marko/database` is bound in the container. Install the database package and ensure it is configured before expecting this tool to appear.

### Multiple Claude Code instances disconnect MCP servers

If you run several Claude Code instances at once (multiple terminals or windows) and notice MCP servers — `marko-mcp` included — repeatedly disconnecting and reconnecting, the cause is **not** Marko. Every Claude Code instance shares a single global `~/.claude.json`, and Claude Code rewrites that file constantly (history, tool-usage counters, session state). Concurrent writes to the one file make Claude Code tear down and reconnect its **entire** MCP fleet in lockstep, so all servers flap together (a known Claude Code issue — see [anthropics/claude-code#25768](https://github.com/anthropics/claude-code/issues/25768), [#28829](https://github.com/anthropics/claude-code/issues/28829)). `marko-mcp` is often the one you notice because it boots a PHP process and is the slowest to re-handshake after each bounce.

You cannot make Marko stop this — it is Claude Code behavior — but you can shrink the blast radius so a reload barely matters, and avoid making it worse.

**1. Scope every MCP server to the project that needs it (the important one).**

The worst amplifier is loading MCP servers into *every* project. A server enabled globally is started — and bounced — in all your projects, including ones that can't even use it (where it just shows "✘ Failed to connect"). Keep each server scoped to where it belongs:

- **`marko-mcp` (and `marko-lsp`, `marko-skills`):** these are enabled **per project** by `marko devai:install`, which writes the marketplace registration and `enabledPlugins` into the project's `.claude/settings.json`. Do **not** also enable them in your global `~/.claude/settings.json` — that makes marko-mcp launch (and fail) in every non-Marko project. If an older global enablement is lurking, remove the `marko-*@marko` entries from `~/.claude/settings.json`; each Marko project still loads them from its own committed `.claude/settings.json`.
- **Other project-specific servers** (a database tool, an internal API, an n8n instance, …): add them to a `.mcp.json` at that project's root so they load only there:

  ```json
  {
    "mcpServers": {
      "n8n-mcp": {
        "type": "stdio",
        "command": "npx",
        "args": ["-y", "n8n-mcp"],
        "env": { "N8N_API_URL": "https://n8n.example.com", "N8N_API_KEY": "…" }
      }
    }
  }
  ```

  Reserve **user (global) scope** (`claude mcp add --scope user`) for tools you genuinely want everywhere.

**2. Reduce the write churn / contention.**

- Run fewer concurrent Claude Code instances against the same global config when you can.
- `export CLAUDE_CODE_SKIP_PROMPT_HISTORY=1` cuts how often `~/.claude.json` is rewritten, which lowers the contention that triggers fleet reloads. Zero downside for most workflows.

**Advanced (rarely worth it): full config isolation.** You can give each project its own `~/.claude.json` via the `CLAUDE_CONFIG_DIR` environment variable, which eliminates the contention entirely. **The catch:** Claude Code stores its login in a way tied to the default config directory (the macOS Keychain item is not shared across `CLAUDE_CONFIG_DIR` values), so each isolated config requires its own `/login` — there is no clean way to share one session across them. Because of that re-login friction, prefer scoping (step 1) and churn reduction (step 2); only reach for `CLAUDE_CONFIG_DIR` isolation if fleet reloads are genuinely disrupting you and you accept logging in per project.

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

The running MCP and LSP servers re-check staleness on every read for `app/` and `modules/`, so completions for code in those directories self-heal without any manual step. If you recently changed `vendor/` or `composer.json`, or you want to force a clean rebuild for any other reason, run:

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
