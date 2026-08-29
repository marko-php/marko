# Task 010: Docs Page and Package README

**Status**: completed
**Depends on**: 009
**Retry count**: 0

## Description
Write the canonical docs page and the package README. This runs last so both describe what was actually built rather than what was planned.

## Context
- `.github/workflows/readme-package-check.yml` runs `bin/check-readme-packages.sh`, which is a **root README catalog drift check**, not a per-package README existence check. Task 001 already added the catalog row to satisfy it; this task writes `packages/roadrunner/README.md` itself, which the catalog row links to. Verify both still line up.
- READMEs are slim pointers per `docs/DOCS-STANDARDS.md`: title, install, quick example, link to the docs page. Do not duplicate the docs page into the README.
- The docs page belongs alongside the other package pages in `packages/docs-markdown/docs/packages/`. Model it on `packages/docs-markdown/docs/packages/database-readwrite.md`, which already has a "Long-Running Processes" section covering closely related ground and should be cross-linked.
- Content that must be covered:
  - Installation, `rr:serve`, and the `.rr.yaml` pointing at `vendor/marko/roadrunner/worker.php`
  - The `.rr.yaml` defaults and **why** they exist: `http.static.dir`, `pool.max_jobs`, `pool.supervisor.max_worker_memory`, `server.env.MARKO_BASE_PATH`
  - **What is not supported and why**: `marko/sse` refuses to boot (and the config override that downgrades it to a warning, plus the fact that a `StreamingResponse` still hard-fails per request); debugbar warns; **file uploads are not supported** and throw loudly (task 002)
  - **The session cookie caveat inherited from #150**: the cookie attaches to the `Response` only when it changes, so the `Response` is not a complete picture of session state on repeat requests
  - The reset lifecycle: what gets reset between requests, in what order, and what that means for anyone writing a stateful singleton. Include the explicit rule — **request-scoped state in a singleton is a cross-user leak under this worker** — with the `Session` and `SessionGuard` fixes from #150 task 009 as the worked example
  - **Do not write to STDOUT.** `echo`, `var_dump`, `print_r` and `dd`-style debugging corrupt the RoadRunner pipes relay. The worker buffers and discards, but developers need to know why their output vanished
  - Link the task 005 spike findings page (`roadrunner-state-leaks.md`) as the record of what was investigated
  - **[CORRECTED]** Do NOT document a PHPStan gap. That decision was reversed: task 001 added `packages/roadrunner/src` to `phpstan.neon`, making this the only non-core package under level-6 analysis — deliberately, because it is the one place where a type error becomes a cross-user security bug. Mention that it IS analysed, if anything.
- Update the Package Inventory in `.claude/architecture.md` — its own checklist requires this after creating a new package.

## Requirements (Test Descriptions)
- [x] `it ships a readme following the package readme standards`
- [x] `it ships a docs page for the roadrunner package`
- [x] `it documents the unsupported packages and the reason for each`
- [x] `it documents that file uploads are unsupported`
- [x] `it documents the session cookie caveat`
- [x] `it documents the stdout restriction`
- [x] `it documents the reset lifecycle and the stateful singleton rule`
- [x] `it lists the package in the architecture package inventory`

## Acceptance Criteria
- All requirements have passing tests
- `bin/check-readme-packages.sh` passes and the root catalog row links to the README this task writes
- Docs page cross-links the `database-readwrite` long-running-processes section and the spike findings page
- Code follows code standards

## Implementation Notes
- Added `packages/roadrunner/README.md` (slim pointer per `docs/DOCS-STANDARDS.md`) and `packages/docs-markdown/docs/packages/roadrunner.md` (canonical reference page).
- Test coverage lives in `packages/roadrunner/tests/DocsTest.php`. The docs page was written in full for requirement 2 (it covers installation, `.rr.yaml` defaults/rationale, STDOUT restriction, reset lifecycle + stateful-singleton rule with `Session`/`SessionGuard`/`Inertia::$shared` examples, the session-cookie caveat, unsupported packages, and API reference), so requirements 3-7 passed immediately once their tests were added — over-implementation relative to strict per-requirement TDD, but a natural consequence of writing one coherent prose document rather than fragmenting it into six unrelated edits.
- Docs page cross-links `/docs/packages/database-readwrite/#long-running-processes` and `/docs/packages/roadrunner-state-leaks/`.
- Root README catalog row (`packages/roadrunner/README.md`) already existed from task 001; `bin/check-readme-packages.sh` passes (92 modules aligned).
- Added a new "Application Server" section to the Package Inventory in `.claude/architecture.md`, and corrected the stale "Current implementors" list under "Resettable Singletons and Long-Running Processes" to include `Inertia` (previously missing after #150 task 009 added it).
- Verified `composer test`: 7086 passed (up from the 7078 baseline by the 8 new tests), no failures.
