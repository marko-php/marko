# Task 001: Add `timeout` to marko-mcp plugin `.mcp.json`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a per-server `"timeout": 60000` (milliseconds) field to the marko-mcp plugin's `.mcp.json`
so Claude Code gives the cold-boot `mcp:serve` initialize handshake 60s of headroom instead of
dropping the server when it misses the default session-init probe window on a brand-new project.

## Context
- Related files:
  - `packages/claude-plugins/plugins/marko-mcp/.mcp.json` (modify — add `timeout` to the `marko` server)
  - `packages/claude-plugins/tests/Unit/MarkoMcpPluginTest.php` (modify — add timeout assertion; TDD)
- Patterns to follow: the existing per-field assertions in `MarkoMcpPluginTest.php`
  (e.g. the tests asserting `marko.command` and `marko.args`). Keep the server key `marko`
  (not `marko-mcp`) and the empty `args` array unchanged.
- Note: `timeout` is a sibling key to `command`/`args` inside `mcpServers.marko`, value in ms.

## Requirements (Test Descriptions)
- [ ] `it declares a numeric marko.timeout field in .mcp.json`
- [ ] `it sets marko.timeout to 60000 milliseconds`
- [ ] `it preserves the marko.command as ${CLAUDE_PLUGIN_ROOT}/bin/marko-mcp`
- [ ] `it preserves marko.args as an empty array`

## Acceptance Criteria
- All requirements have passing tests
- Existing `.mcp.json` shape assertions in `MarkoMcpPluginTest.php` still pass
- `.mcp.json` remains valid JSON
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
