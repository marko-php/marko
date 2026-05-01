# Task 010: Integration test — `devai:install --agents=claude-code` end-to-end shape validation

**Status**: completed
**Depends on**: 003, 004, 005, 008
**Retry count**: 0

## Description
Write an integration test that runs `marko devai:install --agents=claude-code` in a temporary directory simulating an external project, then validates every artifact written matches the shape required by Claude Code's official documentation. This test does not require launching the actual `claude` binary — it asserts only that the file outputs would cause Claude Code to recognize and install the three plugins.

## Context
- Related files:
  - New: `packages/devai/tests/Integration/ClaudeCodeInstallEndToEndTest.php` (or similar — match existing test naming conventions)
  - Read: existing integration tests in `packages/devai/tests/` for patterns (tempdir setup, command invocation, assertion style)
- Test setup must:
  - Create a tempdir with a minimal composer.json that requires marko/devai
  - Either `composer install` it (slow, real) or stub the vendor structure (fast, mock) — pick whichever existing tests do
  - Run the `devai:install` command via the testable CLI invocation pattern (not shell-spawning)
  - Capture all files written
- This test is the autonomous-execution gate that the broken state from PR #41 has been fixed. The user will additionally do a manual `claude plugin list` verification post-orchestration (out of scope for this task).
- Note: the `--agents=claude-code` flag is the same as the manual test in `~/Sites/my-app-local`. This test is the automated regression equivalent.
- **Mode coverage**: this task adds two integration tests, each in its own file, exercising both branches of the monorepo-vs-external-project detection added in Task 008:
  - `ClaudeCodeInstallExternalProjectTest.php` — fixture WITHOUT a sibling `packages/claude-plugins/` directory (simulates an installed project requiring marko/devai via Composer). Asserts settings.json uses the github source shape.
  - `ClaudeCodeInstallMonorepoTest.php` — fixture WITH a stub `packages/claude-plugins/` directory present (simulates dogfooding inside the marko monorepo). Asserts settings.json uses the path/local source shape decided in Task 001.

## Requirements (Test Descriptions)
- [x] `running devai:install --agents=claude-code in a tempdir creates AGENTS.md at the project root`
- [x] `running devai:install --agents=claude-code creates CLAUDE.md at the project root`
- [x] `CLAUDE.md content includes the verbatim authority directive about skills as canonical spec`
- [x] `CLAUDE.md content includes the LSP verification gate directive`
- [x] `running devai:install --agents=claude-code creates .claude/settings.json`
- [x] `.claude/settings.json contains extraKnownMarketplaces.marko entry with the expected source shape`
- [x] `.claude/settings.json contains enabledPlugins with all three plugin entries set to true`
- [x] `running devai:install --agents=claude-code does not create .claude/plugins/marko/.lsp.json (legacy broken path)`
- [x] `running devai:install --agents=claude-code does not invoke "claude mcp add" subprocess (legacy approach)`
- [x] `running devai:install --agents=claude-code creates the install marker .marko/devai.json with the new shape`
- [x] `external-project fixture (no sibling packages/claude-plugins) produces settings.json with the github source shape`
- [x] `monorepo fixture (with stub packages/claude-plugins) produces settings.json with the path/local source shape per Task 001`
- [x] `re-running devai:install on a project that already has the legacy .lsp.json or a registered marko-mcp server cleans them up only when --force is passed (legacy artifact cleanup is gated by --force, consistent with the new idempotency policy)`
- [x] `re-running devai:install on a project that already has extraKnownMarketplaces.marko in settings.json (without --force) exits with a loud Marko exception and a non-zero exit code`
- [x] `re-running devai:install --force on a project with prior extraKnownMarketplaces.marko succeeds and overwrites marko-related keys`
- [x] `re-running devai:install --force preserves unrelated user keys in the existing .claude/settings.json`

## Acceptance Criteria
- All requirements have passing tests
- The integration test runs in under 30 seconds on average hardware (fast feedback during development)
- `composer test` (which excludes destructive integration tests) — confirm whether this test belongs in the destructive group or the fast group; place it in fast unless it requires real `composer install` operations
- Test cleans up its tempdir on completion regardless of pass/fail

## Implementation Notes

### Files created
- `packages/devai/tests/Integration/ClaudeCodeInstallExternalProjectTest.php` — 16 tests covering the external-project fixture, idempotency, force-overwrite, and key-preservation scenarios
- `packages/devai/tests/Integration/ClaudeCodeInstallMonorepoTest.php` — 7 tests covering the monorepo fixture (path/local source shape)

### Bug fixed
The test "re-running devai:install on a project that already has `extraKnownMarketplaces.marko` in settings.json (without `--force`) exits with a loud Marko exception" was calling `integExternalRunInstall()` twice. The first call writes `.marko/devai.json`; on the second call, `InstallationOrchestrator::install()` detects that marker file and returns early with `status: skipped` — never reaching `ClaudeCodeAgent::writeSettings()` and its `assertNotAlreadyRegistered()` check, so `DevAiInstallException` was never thrown.

Fixed by replacing the two-call approach with a single call that pre-populates `settings.json` (simulating what a prior install would have written) without creating the `.marko/devai.json` marker. This lets the orchestrator pass through to `writeSettings()`, where the settings-level idempotency guard fires correctly.

### Final test counts
- Integration suite: 23/23 passed
- Full devai suite: 140/141 passed (1 pre-existing failure in `SkillsDistributorTest` unrelated to this task)
