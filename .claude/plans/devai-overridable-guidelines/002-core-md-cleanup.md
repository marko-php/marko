# Task 002: core.md cleanup — curate to behavior-shaping rules, drop dead links, point depth at the MCP

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Two changes to the shipped core guidelines (`core.md`), which renders into AGENTS.md as the `### marko/core` subsection for **every** tool:

1. **Remove the dead `## See Also` block** (lines 21–24) pointing to `.claude/architecture.md`, `.claude/testing.md`, `.claude/code-standards.md` — files that never exist in a generated project and that leak Claude-Code-specific paths into the tool-agnostic AGENTS.md.
2. **Curate the inline rule set to "Bucket B"** — the behavior-shaping, NOT-lintable, consumer-relevant rules that shape what an agent writes *by default* and that neither the LSP (can't express) nor an un-invoked MCP (pull-based) will supply proactively. Replace the dead links with a concise pointer to the `search_docs` MCP tool for depth, and an explicit note that the LSP enforces the lintable/formatting rules.

This is the single source for the shipped baseline. It must stay terse — a constitution, not a manual, and NOT a copy of the monorepo's `code-standards.md`.

## Context
- File: `packages/devai/resources/ai/guidelines/core.md`.
- Audience is a developer **implementing an app ON Marko**, NOT a framework contributor. The monorepo's `.claude/code-standards.md` (459 lines) is mostly auto-fixed-by-tooling or contributor-process content — do **not** port it wholesale.
- **Keep** the existing inline rules: Strict Types, Constructor Property Promotion, No Final Classes, No Magic Methods, Type Declarations, Loud Errors (`MarkoException` with `message`/`context`/`suggestion`).
- **Add** these four high-value behavior-shaping rules currently missing (each one terse — a sentence + at most a tiny example):
  - **No traits** — use explicit constructor composition; traits hide where behavior comes from.
  - **Interface over driver** — depend on the interface package (`marko/log`), never the driver (`marko/log-file`); let the app choose the driver.
  - **Constructor injection only** — inject dependencies via constructor; never use a service locator / `Container::get()` in app code.
  - **Config is the source of truth** — defaults live in `config/*.php`; config getters have no fallback parameter (they throw `ConfigNotFoundException`); `$_ENV` is referenced only in config files.
- **Exclude** (do NOT inline — note where they belong instead):
  - Bucket A (LSP/formatter-enforced): import org, PSR-12, multiline params, typed constants, `@throws` consolidation. Add one line stating the LSP/formatter enforces these so the agent need not police them.
  - Bucket C (deep "how X works": DI resolution order, plugin sortOrder, route-conflict rules, scoped-config cascade): covered by `search_docs` — point to it.
  - Bucket D (contributor process: PR review, release, sibling-module authoring): never shipped.
- The replacement pointer must be tool-agnostic — reference the `search_docs` MCP tool generically; never name `.claude/` or any single tool's directory.
- Tests asserting AGENTS.md / core guideline content live under `packages/devai/tests/` — find and update any that assert the old content or `.claude/` references.

## Requirements (Test Descriptions)
- [x] `it does not reference any dot claude path in the rendered core guidelines`
- [x] `it retains the baseline coding rules in the core guidelines`
- [x] `it includes the no-traits rule`
- [x] `it includes the interface-over-driver rule`
- [x] `it includes the constructor-injection rule`
- [x] `it includes the config-is-source-of-truth rule`
- [x] `it points to the search_docs mcp tool for deeper documentation`
- [x] `it notes that the LSP enforces the lintable formatting rules`
- [x] `it stays under a terse line budget (no wholesale code-standards copy)`

## Acceptance Criteria
- All requirements have passing tests
- No `.claude/` substring remains in `core.md`
- The four behavior-shaping rules are present and terse; no Bucket A/C/D content is inlined
- Existing AGENTS.md content tests updated to match
- Code follows code standards

## Implementation Notes
- Replaced the dead `## See Also` block (lines 21-24) with two new sections: `## Formatting and Linting` (LSP note) and `## Deeper Documentation` (search_docs pointer).
- Added four new behavior-shaping rules: No Traits, Interface Over Driver, Constructor Injection Only, Config Is the Source of Truth.
- All six original baseline rules retained verbatim.
- Final `core.md` is 28 lines (well under the 60-line budget).
- New test file: `packages/devai/tests/Unit/Guidelines/CoreGuidelinesTest.php` with 9 tests matching the requirement descriptions exactly.
- No existing tests required updating — none asserted the old `.claude/` content in `core.md`.
