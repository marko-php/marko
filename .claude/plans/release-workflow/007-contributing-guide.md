# Task 007: CONTRIBUTING.md

**Status**: completed
**Depends on**: 003, 004, 005, 006
**Retry count**: 0

## Description
Create a concise CONTRIBUTING.md that documents the standard development workflow, branch naming, commit format, and PR conventions. This is the single source of truth for how to contribute to Marko.

## Context
- Related files: `CONTRIBUTING.md` (new, root of repo)
- Depends on all other tasks so it accurately references the auto-labeling, templates, and release process
- Link to `.claude/code-standards.md` for PHP-specific coding rules (don't duplicate)
- Keep it practical and scannable — avoid walls of text

## Requirements (Test Descriptions)
- [x] `it documents the standard workflow: pull develop, create branch, make changes, push, create PR`
- [x] `it documents branch naming conventions: feature/{name}, fix/{name}, docs/{name}`
- [x] `it documents conventional commit format with all type prefixes`
- [x] `it documents PR conventions: title format, Closes #N for issues`
- [x] `it explains auto-labeling from PR title prefix`
- [x] `it provides a release process overview for maintainers`
- [x] `it links to code standards rather than duplicating them`

## Acceptance Criteria
- Concise and scannable (use tables and short sections)
- Covers the complete workflow from branch creation to PR merge
- Matches the conventions established by the other tasks in this plan
- No PHP code standards (those belong in .claude/code-standards.md)

## Implementation Notes
- Created `/Users/markshust/Sites/marko/CONTRIBUTING.md` at repo root
- Workflow section uses a shell code block covering the full branch-to-PR flow
- Branch naming and commit types each use tables for scannability
- Auto-labeling table maps all 7 types plus `!` suffix to their GitHub labels
- Release process documents `./bin/release.sh <version>` with a note that it handles everything end-to-end
- PHP code standards linked to `.claude/code-standards.md` — not duplicated
