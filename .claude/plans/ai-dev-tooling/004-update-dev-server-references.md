# Task 004: Update all dev-server references across monorepo

**Status**: pending
**Depends on**: 003, 002
**Retry count**: 0

> Depends on task 002 to serialize monorepo-wide edits to shared files (`composer.json`, `.claude/architecture.md`, `docs/`, `packages/framework/tests/RootComposerJsonTest.php`) and avoid merge conflicts from parallel runs.

## Description
Update all references to the old `marko/dev-server` package name throughout the monorepo. Since the PHP namespace is likely unchanged, this is primarily a composer.json/docs sweep.

## Context
- Root composer.json
- Other packages' composer.json require statements
- `.claude/architecture.md` package inventory table
- `docs/` references
- `composer.json` scripts (if any reference the old path)

## Requirements (Test Descriptions)
- [ ] `it has zero grep hits for marko/dev-server in composer.json files outside the devserver package`
- [ ] `it updates .claude/architecture.md package inventory to reference marko/devserver`
- [ ] `it runs the full test suite clean after the rename propagates`
- [ ] `it runs composer dump-autoload without errors`

## Acceptance Criteria
- Monorepo-wide `composer test` passes
- `composer dump-autoload` succeeds
- No stale references remain

## Implementation Notes
(Filled in by programmer during implementation)
