# Task 002: Add framework cache warm-up step to `devai:install`

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a `warmFrameworkCaches()` step to `InstallationOrchestrator::install()` that compiles the
discovery cache and rebuilds the code index during install, so a project that has never booted
has its caches warm before the first Claude session — making the first `mcp:serve` handshake
hit the fast (~0.2s) path instead of compiling discovery + lazily building the index inside
Claude Code's probe window. Follows the existing graceful, non-fatal `buildDocsIndex()` pattern.

## Context
- Related files:
  - `packages/devai/src/Installation/InstallationOrchestrator.php` (modify — add private
    `warmFrameworkCaches(string $projectRoot, string $markoBin): void`, call it in `install()`
    immediately before `$this->buildDocsIndex(...)`)
  - `packages/devai/tests/Unit/Installation/InstallationOrchestratorTest.php` (modify — add tests)
- Patterns to follow:
  - Mirror `buildDocsIndex()`: call `$this->runner->run($markoBin, [$command])`, branch on
    `($result['exitCode'] ?? 1) === 0`, append a `[scope] ...` line to `$this->log`, never throw.
  - IMPORTANT — use the test file's local `makeRecordingRunner()` helper, NOT `devaiRunner()`
    from `tests/Helpers.php`. The existing `docs-fts:build` tests in
    `InstallationOrchestratorTest.php` use `makeRecordingRunner()`, whose `calls` are positional
    tuples `[$command, $args]` (so `$call[0]` is the binary, `$call[1]` is the args array).
    `devaiRunner()` records ASSOCIATIVE arrays (`['command' => ..., 'args' => ...]`) and is used by
    a different test file — `$call[1]` would be undefined there. Match the existing file's helper.
  - Test pattern (success): assert recorded runner calls with
    `fn ($call) => in_array('discovery:cache', $call[1], true)` (and `indexer:rebuild`), checking
    `$buildCall[0]` is `$this->tempRoot . '/vendor/bin/marko'` — identical to the existing
    `runs docs-fts:build during install` test.
  - Test pattern (failure): inject an inline anonymous `CommandRunnerInterface` returning a
    non-zero `exitCode` with a `stderr` message, then assert the install log contains a helpful
    line and the returned `status` is still `installed` — exactly like the existing
    `records a helpful log line when the docs index build fails` test.
- Commands to run:
  - `discovery:cache` — compiles preferences/plugins/observers/commands. Lives in `marko/core`,
    so it is ALWAYS present in any project that has `vendor/bin/marko`.
  - `indexer:rebuild` — rebuilds `.marko/index.cache`. Lives in `marko/codeindexer`, which is a
    SEPARATE package and may be ABSENT in a minimal devai install. Run it unconditionally and rely
    on the non-fatal exit-code handling: an absent command exits non-zero, which must be logged as
    a helpful, non-fatal warning (same shape as `buildDocsIndex`'s failure branch) and must not
    fail the install. Do NOT throw or abort if `indexer:rebuild` is unrecognized.
- Order: run `discovery:cache` first, then `indexer:rebuild`, then (existing) `buildDocsIndex()`.
  Each `marko` invocation is a fresh process; compiling the discovery cache first means the later
  `indexer:rebuild` and `docs-fts:build` boots are already warm.

## Requirements (Test Descriptions)
- [ ] `it runs discovery:cache during install` (assert via `makeRecordingRunner()` recorded calls, `$call[1]`)
- [ ] `it runs indexer:rebuild during install`
- [ ] `it runs discovery:cache before indexer:rebuild` (and both before docs-fts:build)
- [ ] `it targets the project vendor/bin/marko for warm-up commands` (assert `$call[0]` is `<tempRoot>/vendor/bin/marko`)
- [ ] `it records a success log line when a warm-up command succeeds`
- [ ] `it records a helpful log line when a warm-up command fails`
- [ ] `it still returns installed status when a warm-up command fails`
- [ ] `it still returns installed status when indexer:rebuild is unavailable` (codeindexer absent: runner returns non-zero exit; install must complete with a non-fatal log line and `status === 'installed'`)

## Acceptance Criteria
- All requirements have passing tests
- Warm-up never throws and never changes the returned `status` from `installed`, even when
  `indexer:rebuild` is unavailable (codeindexer not installed) or any warm-up command exits non-zero
- Tests use the test file's local `makeRecordingRunner()` (positional-tuple calls), not `devaiRunner()`
- Existing `InstallationOrchestrator` tests (including `docs-fts:build` and skip cases) still pass
- No decrease in test coverage
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
