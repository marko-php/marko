# Task 011: `packages/claude-plugins/README.md` per Package README Standards

**Status**: completed
**Depends on**: 002

> Note: per project memory, `doc-updater` in the post-implementation pipeline auto-updates package READMEs. This task writes only the slim-pointer shell (title + one-paragraph description + install command + docs link); doc-updater may overwrite or extend after orchestration.
**Retry count**: 0

## Description
Write the README for the new `packages/claude-plugins/` package, following the project's Package README Standards documented in `docs/DOCS-STANDARDS.md` (slim pointer style: title, brief intro, install command, quick example, link to full docs). This task runs last so the README accurately reflects everything actually built.

## Context
- Related files:
  - New: `packages/claude-plugins/README.md`
  - Read: `docs/DOCS-STANDARDS.md` for the slim-pointer convention
  - Read: existing package READMEs for tone and shape (e.g., `packages/devai/README.md`, `packages/codeindexer/README.md`)
- Note: per memory, the doc-updater agent in the post-implementation pipeline auto-updates package READMEs and docs pages. This task may be redundant if doc-updater handles new packages — but writing it explicitly here ensures the README exists at orchestration completion. If doc-updater later supersedes it, the auto-update wins.
- README should mention:
  - What the package is (a Claude Code marketplace shipping three plugins)
  - The three plugins it contains (marko-skills, marko-lsp, marko-mcp) with one-line descriptions each
  - How users install (typically not directly — `marko devai:install --agents=claude-code` does it)
  - Pointer to the full docs page (likely under `docs/src/content/docs/ai-assisted-development/`)

## Requirements (Test Descriptions)
- [x] `README.md starts with the package title (# marko/claude-plugins or similar)`
- [x] `README.md includes a one-paragraph description of what the package provides`
- [x] `README.md lists all three plugins (marko-skills, marko-lsp, marko-mcp) with one-line descriptions`
- [x] `README.md includes the install command via marko devai:install`
- [x] `README.md links to the full docs page in the docs site`
- [x] `README.md follows the slim-pointer convention from docs/DOCS-STANDARDS.md (no exhaustive feature lists, no inline code samples beyond a quick install example)`

## Acceptance Criteria
- All requirements have passing tests (or are self-evident content checks against the file)
- README matches the tone of other package READMEs in the monorepo
- The full substantive documentation lives in the docs site, not the README

## Implementation Notes
- Wrote `packages/claude-plugins/README.md` as a slim-pointer following `docs/DOCS-STANDARDS.md` Package README Format: title + one-paragraph description + Installation section (devai:install primary, manual /plugin commands secondary) + What's Included table (one line per plugin) + Documentation link to `https://marko.build/docs/ai-assisted-development/`.
- Added `packages/claude-plugins/tests/Unit/ReadmeTest.php` with 6 tests covering all requirement checkboxes; all 6 pass alongside the existing 44 suite tests (50 total, 147 assertions).
- README is 27 lines — well within the 80-line slim-pointer limit tested by the suite.
