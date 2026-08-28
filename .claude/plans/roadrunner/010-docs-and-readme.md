# Task 010: Docs Page and Package README

**Status**: pending
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
  - The known gap that PHPStan does not analyze this package
- Update the Package Inventory in `.claude/architecture.md` — its own checklist requires this after creating a new package.

## Requirements (Test Descriptions)
- [ ] `it ships a readme following the package readme standards`
- [ ] `it ships a docs page for the roadrunner package`
- [ ] `it documents the unsupported packages and the reason for each`
- [ ] `it documents that file uploads are unsupported`
- [ ] `it documents the session cookie caveat`
- [ ] `it documents the stdout restriction`
- [ ] `it documents the reset lifecycle and the stateful singleton rule`
- [ ] `it lists the package in the architecture package inventory`

## Acceptance Criteria
- All requirements have passing tests
- `bin/check-readme-packages.sh` passes and the root catalog row links to the README this task writes
- Docs page cross-links the `database-readwrite` long-running-processes section and the spike findings page
- Code follows code standards

## Implementation Notes
