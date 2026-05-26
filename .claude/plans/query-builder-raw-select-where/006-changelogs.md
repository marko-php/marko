# Task 006: CHANGELOG Entry (root monorepo `/CHANGELOG.md`)

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Add an entry to the root monorepo `/CHANGELOG.md` under its existing `## [Unreleased]` section. The marko monorepo uses a single root CHANGELOG (auto-generated from merged PR titles by `bin/release.sh` for tagged releases; manual entries permitted under `## [Unreleased]`).

There are **no per-package CHANGELOG files** in `packages/database/`, `packages/database-mysql/`, or `packages/database-pgsql/` — earlier drafts of this plan incorrectly assumed otherwise. Verified via `find` across the monorepo: only `/CHANGELOG.md` exists at the root (excluding files inside `vendor/` directories).

## Context

### Files to edit
- `/CHANGELOG.md` at the marko monorepo root (read first to confirm the existing `## [Unreleased]` section and the `### New Features` style).

### Existing root CHANGELOG style (verified by reading the file)

The root CHANGELOG uses Keep-a-Changelog as the format reference but its actual section headings are:
- `### New Features`
- `### Bug Fixes`
- `### Maintenance`

(NOT `### Added` / `### Changed` / `### Fixed`.)

Entries are written as PR-title-style bullets, e.g.:
```
* feat(page-cache): full-page HTTP cache with file driver, tag invalidation, and entity bridge by @michalbiarda in https://github.com/marko-php/marko/pull/58
```

### Entry to add under `## [Unreleased]` → `### New Features`

```
* feat(database): add `QueryBuilderInterface::selectRaw(string, array)` and `::whereRaw(string, array)` with positional bindings, denylist validation, and `MySqlQueryBuilder` / `PgSqlQueryBuilder` / `RepositoryQueryBuilder` implementations
```

If a PR URL is known by the time this task runs, append `by @<author> in <PR-URL>` to match the file's style.

### Out of scope for this task
- Per-package CHANGELOG files (they don't exist; do NOT create them).
- README updates (handled by `doc-updater` agent per `.claude/pipeline.md`).
- Mentioning `orWhereRaw` or `havingRaw` (those are explicitly deferred per `_plan.md` "Deferred").
- Mentioning a "Changed" entry about refactoring `orderByRaw` — `orderByRaw` does not exist in the codebase and is not modified by this plan.

## Requirements (Test Descriptions)

There is no precedent for `Unit/ChangelogTest.php` in this monorepo (verified by `find . -name 'ChangelogTest.php'`). Do NOT invent one. The verification for this task is manual / human-reviewable:

- [ ] The root `/CHANGELOG.md` has an `## [Unreleased]` section.
- [ ] Under `## [Unreleased]` → `### New Features` there is a bullet mentioning `QueryBuilderInterface::selectRaw` and `QueryBuilderInterface::whereRaw`.
- [ ] No bullet mentions `orWhereRaw`, `havingRaw`, or `orderByRaw` (those are explicitly deferred / non-existent).

These are validated as part of the PR review, not as automated tests.

## Acceptance Criteria
- The root `/CHANGELOG.md` has the entry above (or an equivalent paraphrase matching the file's existing style).
- No new files are created in `packages/database*/` for CHANGELOG purposes.
- `composer test` is fully green (this task doesn't add tests, but the previous tasks' tests must still pass).
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
