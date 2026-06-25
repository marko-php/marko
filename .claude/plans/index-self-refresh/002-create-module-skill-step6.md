# Task 002: Rewrite create-module SKILL.md Step 6

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Step 6 of the create-module skill ("Verify the module is discovered") is the direct source of two wrong agent reflexes seen in real sessions: it instructs `composer dump-autoload` (app-module autoloading is automatic at runtime via `ModuleAutoloader`) and frames `list_modules` as a discovery gate (which trained the agent to distrust its own writes and reach for `indexer:rebuild`). Rewrite it to reflect reality: the module exists because you wrote it, the runtime discovers and autoloads it live on the next request, and the AI tooling self-refreshes on read (task 001) — so introspection is an optional cross-check, never a gate.

## Context
This is a documentation/skill-content task — no unit test. Verification is content review: the new text must not contain the removed instructions and must state the correct model.

- Related files:
  - `packages/claude-plugins/plugins/marko-skills/skills/create-module/SKILL.md` — Step 6 (lines 74–80) AND the "Verification" section (lines 82–84). Both contain gate-framing that must change.
- Exact strings to remove/rewrite (verified in source):
  - Line 76: `"After installing or registering the module, call the MCP tool list_modules. The new module should appear in the list. If not, check that:"` — the "If not, check that" framing trains distrust of the agent's own write. Reframe: the module exists because you wrote it; runtime discovers and autoloads it live next request.
  - Line 79: `"Composer has run (composer dump-autoload or composer update)"` — REMOVE entirely; app-module autoloading is automatic via `ModuleAutoloader` at runtime, never needs `composer dump-autoload`.
  - Line 84: `"Then call the list_modules MCP tool to confirm the module is discovered by the framework."` — soften from a discovery gate to an optional cross-check; keep the LSP-diagnostics-as-verification-gate sentence (that part is legitimate and stays).
- Note line 78 (`extra.marko.module: true`) and line 80 (psr-4 resolves) are legitimate structural checks and may stay, reframed as "if you want to double-check structure" rather than "if the module didn't appear."
- Patterns to follow: the skill's existing terse, imperative voice; consistency with `concepts/modularity.md` and `packages/codeindexer.md` (rewritten in task 003).

## Requirements (Test Descriptions)
- [x] `it removes the composer dump-autoload instruction from Step 6`
- [x] `it removes the framing of list_modules as a required discovery gate`
- [x] `it states module discovery and autoloading happen live at runtime with no build step`
- [x] `it states the MCP/LSP index self-refreshes on read so indexer:rebuild is not needed to see newly created code`
- [x] `it keeps validate_module as an optional structural cross-check rather than an existence check`

## Acceptance Criteria
- No occurrence of `composer dump-autoload` (or `composer update` as a discovery fix) remains in the skill.
- No language tells the agent to run `indexer:rebuild` to make freshly-created code visible.
- The verification section frames introspection tools as optional cross-checks.
- Wording is consistent with the rewritten docs (task 003).

## Implementation Notes
- Step 6 (lines 74–80): Replaced the "If not, check that" framing with a positive assertion ("The module exists because you wrote it") plus runtime/self-refresh description. Removed the `composer dump-autoload` bullet entirely. Kept `extra.marko.module: true` and psr-4 structural checks, reframed under "If you want to double-check structure". Added an explicit sentence that `list_modules`/`validate_module` are optional cross-checks, not gates.
- Verification section (lines 82–84): Kept LSP-diagnostics-as-gate sentence intact. Replaced "Then call the list_modules MCP tool to confirm the module is discovered by the framework" with a sentence framing `list_modules` as an optional cross-check that is not required for the module to be active.

## Acceptance Criteria Status
- No occurrence of `composer dump-autoload` remains in the skill. PASS
- No language tells the agent to run `indexer:rebuild`. PASS
- Verification section frames introspection tools as optional cross-checks. PASS
- Wording consistent with self-refresh behavior. PASS
