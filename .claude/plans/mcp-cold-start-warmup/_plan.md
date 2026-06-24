# Plan: MCP Cold-Start Warm-Up

## Created
2026-06-24

## Status
completed

## Objective
Make the `marko-mcp` Claude Code plugin connect cleanly on a brand-new project's first
session — eliminating the need to manually `/mcp` reconnect — by (1) giving the cold-boot
MCP handshake timeout headroom and (2) warming the framework's discovery + code-index
caches during `devai:install` so the first `mcp:serve` handshake hits the warm path.

## Related Issues
none (root cause references upstream anthropics/claude-code#60224 for context only)

## Discovery Notes
Root cause (already diagnosed this session): Claude Code starts plugin **stdio** MCP servers
asynchronously and non-blocking; stdio servers do **not** auto-reconnect; if a server's
`initialize` handshake misses Claude Code's session-init probe window it is silently dropped
for that session (anthropics/claude-code#60224). On a brand-new project the first cold boot
(module discovery + discovery-cache compile + lazy code-index build) is the slow part that
misses the window. A warm project boots `mcp:serve` in ~0.2s.

Codebase findings:
- `packages/claude-plugins/plugins/marko-mcp/.mcp.json` — `{ mcpServers: { marko: { command, args:[] } } }`.
  Claude Code honors an optional per-server `"timeout"` (ms) field here.
- `packages/claude-plugins/tests/Unit/MarkoMcpPluginTest.php` — already asserts the `.mcp.json`
  shape field-by-field; add a timeout assertion (TDD).
- `packages/devai/src/Installation/InstallationOrchestrator.php` — `install()` already ends with
  a graceful, non-fatal `buildDocsIndex()` that shells out via `CommandRunnerInterface` and appends
  to `$this->log`. The framework warm-up is a direct sibling method following the same pattern.
- Core commands available to warm caches: `discovery:cache` (compiles preferences/plugins/
  observers/commands — `packages/core/src/Commands/DiscoveryCacheCommand.php`) and
  `indexer:rebuild` (rebuilds `.marko/index.cache` — `packages/codeindexer/src/Commands/RebuildIndexCommand.php`).
- `packages/devai/tests/Unit/Installation/InstallationOrchestratorTest.php` records every runner
  call and asserts which commands ran (`in_array('docs-fts:build', $call[1], true)` pattern). NOTE:
  those tests use the file-local `makeRecordingRunner()` helper whose `calls` are POSITIONAL tuples
  `[$command, $args]` (`$call[0]` = binary, `$call[1]` = args). Do NOT use `devaiRunner()` from
  `tests/Helpers.php` — it records ASSOCIATIVE arrays (`['command'=>,'args'=>]`) and is used by a
  different test file. The warm-up tests reuse `makeRecordingRunner()`.

Resolved assumptions: warm-up runs both commands, non-fatal/logged, placed before `buildDocsIndex()`.

## Scope

### In Scope
- Add a `"timeout": 60000` field to the marko-mcp plugin `.mcp.json` (+ test assertion).
- Add a `warmFrameworkCaches()` step to `InstallationOrchestrator::install()` that runs
  `discovery:cache` and `indexer:rebuild` via the injected `CommandRunnerInterface`, logging
  success/failure non-fatally, placed before `buildDocsIndex()` (+ tests).

### Out of Scope
- Changing how Claude Code itself probes/connects MCP servers (upstream behavior).
- Adding a `timeout` to the per-agent `McpRegistration` written for non-Claude agents (Codex,
  Cursor, etc.) — different config surfaces; not part of this ask.
- Any change to `mcp:serve` boot internals or the `bin/marko-mcp` shim.
- README/docs edits (handled by the doc-updater pipeline post-implementation).

## Success Criteria
- [ ] Plugin `.mcp.json` declares `marko.timeout = 60000`; existing shape assertions still pass.
- [ ] `devai:install` runs `discovery:cache` and `indexer:rebuild` and logs the outcome.
- [ ] Warm-up failures are non-fatal: install still returns `installed` with a helpful log line.
- [ ] All tests passing.
- [ ] Code follows project standards.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add `timeout` to marko-mcp plugin `.mcp.json` | - | completed |
| 002 | Add framework cache warm-up step to `devai:install` | - | completed |

## Architecture Notes
- Mirror the existing `buildDocsIndex()` pattern in `InstallationOrchestrator` exactly: resolve
  `$markoBin`, call `$this->runner->run($markoBin, [$command])`, branch on `exitCode`, append a
  `[scope] ...` line to `$this->log`. Never throw — a failed warm-up must not fail the install.
- Each `marko` CLI invocation is a fresh process; running `discovery:cache` first means the
  later `indexer:rebuild`, `docs-fts:build`, and the eventual `mcp:serve` all boot warm.
- The `.mcp.json` `timeout` is in milliseconds (Claude Code convention); 60000 = 60s headroom.

## Risks & Mitigations
- Risk: warm-up commands not present in a minimal install (e.g. `marko/codeindexer` absent, so
  `indexer:rebuild` is unrecognized) → mitigate by treating a non-zero exit as a logged, non-fatal
  warning, identical to `buildDocsIndex`. `discovery:cache` ships in `marko/core` and is always
  present; only `indexer:rebuild` can be missing. Task 002 includes an explicit test for the
  codeindexer-absent path (install still returns `installed`).
- Risk: warm-up slows `devai:install` noticeably → acceptable; it is a one-time install cost that
  trades for a clean first session, and runs after the user-facing install work is done.
- Risk: `MCP_TIMEOUT`/per-server timeout >60s ignored by some Claude Code versions → 60000 stays
  within the honored range.
