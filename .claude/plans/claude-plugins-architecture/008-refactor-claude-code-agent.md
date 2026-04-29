# Task 008: Refactor `ClaudeCodeAgent` — settings.json writer, CLAUDE.md authority directives, delete legacy registrations

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Refactor `packages/devai/src/Agents/ClaudeCodeAgent.php` to use the official Claude Code plugin distribution path. Delete the broken `.lsp.json` writer and the direct `claude mcp add` calls. Add a writer for `.claude/settings.json` that registers the marko marketplace via `extraKnownMarketplaces` and enables all three plugins via `enabledPlugins`. Upgrade `CLAUDE.md` content to include the authority directive ("skill is the spec, don't infer from siblings", "LSP diagnostics are the verification gate"). Add monorepo-vs-external-project detection so the marketplace source resolves correctly in both contexts.

## Context
- Related files:
  - Modify: `packages/devai/src/Agents/ClaudeCodeAgent.php`
  - Modify: `packages/devai/src/Contract/SupportsLsp.php` and `SupportsMcp.php` — these contracts may no longer fit the new model; either remove or adapt for non-Claude-Code agents that still benefit (none currently — Claude Code was the only consumer)
  - Modify: `packages/devai/src/Installation/InstallationOrchestrator.php` — orchestration may need adjustment if MCP/LSP registration steps are merged into the settings.json write
  - Modify: `packages/devai/src/ValueObject/LspRegistration.php`, `McpRegistration.php` — may become obsolete or repurposed
  - Modify: existing tests in `packages/devai/tests/` for ClaudeCodeAgent — current tests assert legacy file writes; rewrite to assert settings.json shape + CLAUDE.md content
  - Modify: `packages/devai/src/Skills/SkillsDistributor.php` — may become obsolete for Claude Code (since skills now ship via the plugin); other agents still use it
- **devai's role for LSP/MCP is now minimal** per Task 001 finding F8: project-local LSP/MCP overrides do NOT exist in Claude Code. The plugins self-contain their command resolution via shim scripts (Tasks 003, 004). devai writes ONLY the marketplace registration and enabled-plugin list — no path substitution, no `.lsp.json` writes, no `claude mcp add` calls.
- Required output of refactored `ClaudeCodeAgent`:
  - Writes `AGENTS.md` (unchanged from current behavior)
  - Writes `CLAUDE.md` with new content including `@AGENTS.md` import + authority directive + LSP gate language
  - Writes/merges `.claude/settings.json` with:
    ```json
    {
      "extraKnownMarketplaces": {
        "marko": { "source": <per Task 001 findings> }
      },
      "enabledPlugins": {
        "marko-skills@marko": true,
        "marko-lsp@marko": true,
        "marko-mcp@marko": true
      }
    }
    ```
  - Detects monorepo context (presence of `packages/claude-plugins/` adjacent or in CWD ancestry) vs. external-project context, and chooses the right `source` shape per Task 001
  - If existing settings.json has user content unrelated to marko, merges rather than overwriting those keys
  - **Idempotency policy (per Marko's loud-error philosophy)**: if `.claude/settings.json` already has `extraKnownMarketplaces.marko` (or `enabledPlugins` containing any `*@marko` entry), throw a loud Marko exception describing the prior install and suggesting `--force` to re-run. With `--force`, overwrite the marko-related keys (still preserving unrelated user keys). Surface the `--force` flag through the `devai:install` command and the InstallationOrchestrator into the agent.
- Verbatim CLAUDE.md authority directive (or near-verbatim):
  > Marko ships task-oriented skills via the `marko-skills` plugin. When a skill loads, it is the canonical spec — do not infer structure from sibling modules; siblings may have drifted from spec. Use bundled templates verbatim, substitute placeholders only. After writing files, expect LSP diagnostics from `marko-lsp`; resolve all diagnostics before declaring the task complete.

## Requirements (Test Descriptions)
- [x] `writeGuidelines writes AGENTS.md with the existing aggregated package guidelines content`
- [x] `writeGuidelines writes CLAUDE.md including the @AGENTS.md import directive`
- [x] `writeGuidelines writes CLAUDE.md including the verbatim authority directive about skills as canonical spec`
- [x] `writeGuidelines writes CLAUDE.md including the LSP verification gate directive`
- [x] `installation writes .claude/settings.json with extraKnownMarketplaces.marko entry`
- [x] `installation writes .claude/settings.json with enabledPlugins listing marko-skills@marko, marko-lsp@marko, marko-mcp@marko all set to true`
- [x] `installation merges into an existing .claude/settings.json without clobbering unrelated user keys`
- [x] `running install on a project that already has extraKnownMarketplaces.marko throws a loud Marko exception (message + context + suggestion) when --force is not passed`
- [x] `running install with --force on a project that already has extraKnownMarketplaces.marko overwrites the marko-related keys without throwing`
- [x] `running install with --force preserves unrelated user keys in the existing .claude/settings.json (only marko-prefixed keys are touched)`
- [x] `the --force flag is plumbed end-to-end: devai:install command accepts it, InstallationOrchestrator forwards it, ClaudeCodeAgent honors it`
- [x] `monorepo detection: when run from inside the marko monorepo, ClaudeCodeAgent chooses the marketplace source shape decided in Task 001 for monorepo dogfooding (likely a local --plugin-dir hand-off or a path-based source) and the test asserts this exact shape`
- [x] `external-project detection: when run from a project that requires marko/devai via Composer but is not the monorepo, ClaudeCodeAgent chooses the github source shape with the path subdirectory resolved to "packages/claude-plugins"`
- [x] `the test for both monorepo and external-project branches uses two separate test fixtures (a tempdir with packages/claude-plugins present vs absent) rather than mocking the detection`
- [x] `ClaudeCodeAgent no longer writes .claude/plugins/marko/.lsp.json — install also removes any pre-existing one (legacy artifact cleanup, idempotent)`
- [x] `ClaudeCodeAgent no longer invokes claude mcp add directly — install also removes any previously-registered marko-mcp server via claude mcp remove if present (idempotent)`
- [x] `legacy SkillsDistributor invocation for Claude Code agent is removed (skills come via the plugin now)`
- [x] `CLAUDE.md content notes the new plugin-namespaced skill invocation (e.g., /marko-skills:create-module) so users understand the @marko notation in enabledPlugins keys`

## Acceptance Criteria
- All requirements have passing tests (Pest fakes for filesystem; assert exact JSON shape + content presence)
- `composer test` passes for `marko/devai`
- No regressions in non-Claude-Code agents (Codex, Cursor, etc.) — their installers still call SkillsDistributor for their respective standalone-skill paths (Task 009 reroutes their source to the new canonical location)
- All existing pre-existing lint errors in touched files are fixed (per memory feedback)

## Implementation Notes

### Files changed

**Deleted:**
- `packages/devai/src/Contract/SupportsLsp.php` — Claude Code was the only consumer; plugin distribution makes per-project LSP override files obsolete
- `packages/devai/src/ValueObject/LspRegistration.php` — no longer needed once `registerLspServer()` is gone

**Created:**
- `packages/devai/src/Exceptions/DevAiInstallException.php` — new `MarkoException` subclass with `alreadyRegistered(string $projectRoot)` factory. Carries message + context + suggestion per Marko's loud-error policy.
- `packages/devai/src/Contract/SupportsSettings.php` — new interface for agents that write AI-tool settings files (`.claude/settings.json` etc.). `ClaudeCodeAgent` implements it; `InstallationOrchestrator` dispatches on it.

**Modified:**
- `packages/devai/src/Agents/ClaudeCodeAgent.php` — removed `registerMcpServer()`, `registerLspServer()`, `distributeSkills()`. Added `writeSettings(string $projectRoot, bool $force)`. Implements `SupportsGuidelines` + `SupportsSettings` only (no longer `SupportsMcp`, `SupportsLsp`, `SupportsSkills`). New `CLAUDE.md` content includes `@AGENTS.md` import, plugin-namespaced skill examples, authority directive (skill is canonical spec), LSP verification gate.
- `packages/devai/src/Installation/InstallationOrchestrator.php` — removed LSP dispatch block (`SupportsLsp` + `LspRegistration`). Added `SupportsSettings` dispatch. Removed `LspRegistration` import. `McpRegistration` retained for non-Claude agents (Codex, Cursor, etc.).
- `packages/devai/tests/Unit/Agents/ClaudeCodeAgentTest.php` — full rewrite: 23 tests covering all 18 requirements. Uses real tempdirs (no mocking). Renamed helper to `makeClaudeRunner()` to avoid collision with `CodexAgentTest.php`.
- `packages/devai/tests/Unit/Contract/AgentContractTest.php` — updated to replace `SupportsLsp` + `LspRegistration` references with `SupportsSettings`; added test asserting `SupportsLsp` no longer exists.
- `packages/devai/tests/Unit/Installation/InstallationOrchestratorTest.php` — removed `SupportsLsp`/`LspRegistration` from fake agent, removed `lspCalls` assertions. Retained full MCP + skills coverage.
- `packages/devai/tests/Unit/Agents/CodexAgentTest.php` — removed `SupportsLsp` import and the assertion that Codex does not implement it (interface gone entirely).

### Key design decisions

1. **Monorepo detection** — `isMonorepo(string $projectRoot): bool` checks for presence of `packages/claude-plugins/` directory. Simple filesystem check, no config needed. Monorepo gets `{"source": "local", "path": "."}`, external projects get `{"source": "github", "repo": "marko-php/marko"}`.

2. **Idempotency** — throws `DevAiInstallException` if `extraKnownMarketplaces.marko` OR any `*@marko` plugin key exists and `--force` is false. With `--force`, only marko-prefixed keys are replaced; all other user keys in `settings.json` are preserved.

3. **Legacy cleanup** — `writeSettings()` always (not just on `--force`) removes `.claude/plugins/marko/.lsp.json` if present and calls `claude mcp remove marko-mcp` if `claude mcp list` shows it registered. Both operations are idempotent (no error if absent).

4. **`SupportsMcp` kept** — other agents (Codex, Cursor, Copilot, Gemini, Junie) still use `SupportsMcp` + `McpRegistration`. The interface and value object are not deleted.

5. **`SkillsDistributor` kept** — `ClaudeCodeAgent` no longer calls it (skills come via the plugin), but other agents still use it. The class itself is untouched.
