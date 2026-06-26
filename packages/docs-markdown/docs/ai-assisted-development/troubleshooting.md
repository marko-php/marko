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

If you run several Claude Code instances at once (multiple terminals or windows) and notice MCP servers — `marko-mcp` included — repeatedly disconnecting and reconnecting, the cause is **not** Marko. Every Claude Code instance shares a single global `~/.claude.json`, and Claude Code rewrites that file constantly (history, tool-usage counters, session state). Concurrent writes to the one file make Claude Code tear down and reconnect its **entire** MCP fleet in lockstep, so all servers flap together. `marko-mcp` is often the one you notice because it boots a PHP process and is the slowest to re-handshake after each bounce.

This is a known Claude Code issue (see [anthropics/claude-code#25768](https://github.com/anthropics/claude-code/issues/25768), [#28829](https://github.com/anthropics/claude-code/issues/28829)), independent of Marko. There is no Marko setting that fixes it — the reliable workaround is to give each project its own Claude Code config via the `CLAUDE_CONFIG_DIR` environment variable so concurrent instances stop contending on one file.

Add this `claude` wrapper to your shell profile (`~/.zshrc` shown; adapt for bash). It gives each project its own isolated `.claude.json` under `~/.claude-profiles/<project>/` while sharing plugins, skills, commands, hooks, and settings via symlinks. Credentials live in the macOS Keychain and are shared automatically — no re-login per project.

```bash
# Per-project Claude Code config profiles — stops concurrent instances from
# contending on a single ~/.claude.json (which causes MCP servers to flap).
claude() {
  emulate -L zsh
  local src="$HOME/.claude" globalcfg="$HOME/.claude.json" root profile item
  root=$(git rev-parse --show-toplevel 2>/dev/null) || root="$PWD"
  profile="$HOME/.claude-profiles/${root:t}"
  mkdir -p "$profile"
  for item in plugins skills commands hooks settings.json settings.local.json statusline-context.sh config ide; do
    [[ -e "$src/$item" && ! -e "$profile/$item" ]] && ln -s "$src/$item" "$profile/$item"
  done
  # Seed the isolated config once from the real global ~/.claude.json so global
  # MCP servers and plugin enablement carry over into the profile.
  [[ ! -f "$profile/.claude.json" && -f "$globalcfg" ]] && cp "$globalcfg" "$profile/.claude.json"
  CLAUDE_CONFIG_DIR="$profile" command claude "$@"
}
```

Open a new terminal (or `source ~/.zshrc`) and confirm isolation with `claude mcp list` from inside a project — your MCP servers should connect, and `~/.claude.json` should no longer be touched by that instance.

> Note: `CLAUDE_CONFIG_DIR` is honored by the Claude Code CLI but ignored by the VS Code extension, which always uses `~/.claude/`.

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
