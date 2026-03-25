# Plan: Dev Experience Improvements

## Created
2026-03-25

## Status
completed

## Objective
Eliminate friction for new Marko projects: fix docs to match reality, create the skeleton project, add a dev-server guard for missing `public/index.php`, default `marko up` to detached mode, and fix the dev:status PID tracking bug.

## Scope

### In Scope
- Fix getting-started docs pages (first-application.md, project-structure.md) to match real API
- Create `marko/skeleton` package for `composer create-project`
- Add `public/index.php` existence check in DevUpCommand with helpful error
- Flip `marko up` default to detached mode (`--foreground`/`-f` for foreground)
- Fix dev:status PID tracking bug (shell wrapper PID goes stale)
- Update dev-server tests, config, docs, and README

### Out of Scope
- Auto-creating `public/index.php` from dev-server (it's a process manager, not scaffolder)
- Changes to the bootstrap API (already done in previous plan)
- New docs pages beyond fixing existing ones
- Dev-server features beyond the guard and detach default

## Success Criteria
- [ ] `composer create-project marko/skeleton my-app` produces a working project structure
- [ ] `marko up` runs in detached mode by default; `marko up -f` runs in foreground
- [ ] `marko up` in a project without `public/index.php` shows a helpful error
- [ ] `marko dev:status` correctly shows running/stopped state after `marko up`
- [ ] Docs pages match the real `Application::boot()` / `handleRequest()` API
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Fix getting-started docs pages | - | pending |
| 002 | Create marko/skeleton package | - | pending |
| 003 | Add public/index.php guard to DevUpCommand | - | pending |
| 004 | Default marko up to detached mode | 003 | pending |
| 005 | Fix dev:status PID tracking bug | - | pending |
| 006 | Update dev-server docs and README | 003, 004, 005 | pending |
| 007 | Create skeleton package README | 002 | pending |

## Architecture Notes
- Skeleton is `"type": "project"` in composer.json — this enables `composer create-project`
- Detach default flip: change `dev.detach` config to `true`, add `--foreground`/`-f` flag, keep `--detach`/`-d` for explicit override
- PID tracking fix: use `exec()` prefix in shell commands so the shell replaces itself with the command, giving us the real PID. Alternative: use `pgrep` or track by port.
- Dev-server guard checks `public/index.php` existence at the top of `execute()`, before any processes start. This avoids orphaning Docker/frontend processes if the guard throws.

## Risks & Mitigations
- **PID tracking fix complexity**: The `proc_open()` + `proc_get_status()` pattern inherently returns wrapper PIDs. Mitigation: prepend `exec` to commands so the shell process replaces itself with the actual command, preserving the PID.
- **Detach flag backwards compatibility**: Users who had `dev.detach: false` in config and relied on foreground default won't be affected (their config is explicit). Users who relied on the implicit default will now get detached mode.
