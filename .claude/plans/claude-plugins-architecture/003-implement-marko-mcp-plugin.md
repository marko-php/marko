# Task 003: Implement `marko-mcp` plugin (manifest + .mcp.json)

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Build the `marko-mcp` Claude Code plugin under `packages/claude-plugins/plugins/marko-mcp/`. This plugin is a thin manifest that registers the existing `marko mcp:serve` MCP server (provided by `packages/mcp/` package) with Claude Code. Includes `.claude-plugin/plugin.json` (plugin manifest), `.mcp.json` (MCP server registration), and `bin/marko-mcp` (POSIX shell shim that locates `vendor/bin/marko` and execs `marko mcp:serve`).

## Context
- Related files:
  - New: `packages/claude-plugins/plugins/marko-mcp/.claude-plugin/plugin.json`
  - New: `packages/claude-plugins/plugins/marko-mcp/.mcp.json`
  - New: `packages/claude-plugins/plugins/marko-mcp/bin/marko-mcp` (POSIX shell shim, executable bit set)
  - New: `packages/claude-plugins/plugins/marko-mcp/README.md`
  - Read: `packages/mcp/composer.json` and `packages/cli/` to confirm the `marko mcp:serve` invocation
  - Reference: `.claude/plans/claude-plugins-architecture/schemas/mcp.json.example.json` and `schemas/plugin.json.example.json` (output of Task 001) — copy verbatim as starting points
- **`.mcp.json` shape** (verified per Task 001 finding F2): top-level wrapper `mcpServers`, then keyed by server name:
  ```json
  {
    "mcpServers": {
      "marko-mcp": {
        "command": "${CLAUDE_PLUGIN_ROOT}/bin/marko-mcp",
        "args": []
      }
    }
  }
  ```
- **Shim script `bin/marko-mcp`** (POSIX sh, executable):
  1. Locate `marko` binary in priority order: `./vendor/bin/marko` (project-local) → `marko` (global PATH)
  2. If found, exec it with `mcp:serve "$@"`
  3. If not found, print loud error to stderr and exit 1 with a helpful message ("No marko binary found. Run `composer require marko/devai` in this project.")
- Per Task 001 finding F8, project-local LSP/MCP overrides are NOT supported. The shim is the only resolution mechanism. devai's installer does NOT substitute paths.

## Requirements (Test Descriptions)
- [x] `plugin.json declares name "marko-mcp" and a description matching the marketplace.json entry`
- [x] `plugin.json includes author with name "Marko Framework"`
- [x] `plugin.json does not include a version field, allowing git-commit-based versioning`
- [x] `.mcp.json top-level structure is { "mcpServers": { "marko-mcp": {...} } } per Task 001 finding F2`
- [x] `.mcp.json marko-mcp.command is "${CLAUDE_PLUGIN_ROOT}/bin/marko-mcp"`
- [x] `.mcp.json marko-mcp.args is an empty array (subcommand is hardcoded inside the shim)`
- [x] `bin/marko-mcp shim script exists, has POSIX shebang (#!/bin/sh), is committed with executable bit set`
- [x] `bin/marko-mcp searches for marko binary in order: ./vendor/bin/marko then marko on PATH`
- [x] `bin/marko-mcp execs the discovered binary with "mcp:serve" plus any forwarded args`
- [x] `bin/marko-mcp prints a loud error to stderr and exits 1 when no marko binary is found, suggesting "composer require marko/devai"`
- [x] `README.md explains what the plugin registers, how to install via the marko marketplace, and how to verify with claude mcp list`

## Acceptance Criteria
- All requirements have passing tests (PHP-side schema validation against the verified schema from Task 001)
- The plugin directory layout matches Anthropic's documented anatomy (`.claude-plugin/plugin.json` is the only file inside `.claude-plugin/`; `.mcp.json` is at plugin root)
- `composer test` for the claude-plugins package passes

## Implementation Notes
- `plugin.json` uses `author.name: "Marko Framework"` (not "Mark Shust") per task spec, while `author.email` and other metadata fields follow the marketplace.json pattern.
- No `version` field in `plugin.json` — Claude Code resolves version from git commit per finding F6.
- `.mcp.json` uses the nested `{"mcpServers": {...}}` shape confirmed by Task 001 finding F2. The `args` array is explicitly present and empty; `mcp:serve` is hardcoded in the shim so Claude Code passes no subcommand args.
- `bin/marko-mcp` shim is a pure POSIX `/bin/sh` script with `set -e`. Priority: `./vendor/bin/marko` (project-local) then `command -v marko` (global PATH). Both `exec` calls forward `"$@"` for any future flags.
- Executable bit set via `chmod +x` on the shim file.
- All 11 requirements covered by `packages/claude-plugins/tests/Unit/MarkoMcpPluginTest.php` under a single `describe('marko-mcp plugin', ...)` block.
- Full suite (51 tests, 151 assertions) passes. php-cs-fixer reports no changes needed on the test file.
