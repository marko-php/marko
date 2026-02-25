# Plan: Dev Server (marko/dev-server)

## Created
2026-02-25

## Status
in_progress

## Objective
Add command aliases to marko/core and create a new `marko/dev-server` package that provides `dev:up`, `dev:down`, and `dev:status` commands for orchestrating local development services (Docker, frontend tooling, PHP built-in server).

## Scope

### In Scope
- Command alias support in core (`#[Command(name: 'dev:up', aliases: ['up'])]`)
- `dev:up` command: auto-detect and start Docker, frontend (package.json `dev` script), PHP server
- `dev:down` command: stop all managed processes
- `dev:status` command: show running process status
- `config/dev.php` for configuration with `true | string | false` pattern
- Foreground mode (default): combined prefixed output, Ctrl+C stops all
- Background mode (`--detach`): PID file at `.marko/dev.json`, status tracking
- `--port` flag for one-off port override
- Package manager detection (npm, yarn, pnpm, bun)

### Out of Scope
- Tailwind-specific detection (replaced by generic `package.json` `dev` script detection)
- Docker Compose file generation or management
- Production server configuration
- Hot module reloading or live reload

## Success Criteria
- [ ] Command aliases work throughout the CLI system
- [ ] `marko dev:up` auto-detects Docker Compose, runs frontend, starts PHP server
- [ ] `marko up` works as alias for `dev:up`
- [ ] `marko dev:down` / `marko down` stops all processes
- [ ] `marko dev:status` shows running process information
- [ ] Config file `config/dev.php` controls all behavior
- [ ] `--port` and `--detach` flags work as one-off overrides
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add aliases to Command attribute and CommandDefinition | - | completed |
| 002 | Update CommandRegistry for alias indexing | 001 | pending |
| 003 | Update CommandRunner for alias resolution | 002 | pending |
| 004 | Update ListCommand to display aliases | 002 | pending |
| 005 | Update CommandDiscovery to extract aliases | 001 | pending |
| 006 | Create dev-server package scaffold | - | completed |
| 007 | Implement DockerDetector | 006 | pending |
| 008 | Implement FrontendDetector | 006 | pending |
| 009 | Implement PidFile | 006 | pending |
| 010 | Implement ProcessManager | 006 | pending |
| 011 | Implement DevUpCommand | 007, 008, 009, 010 | pending |
| 012 | Implement DevDownCommand | 009, 010 | pending |
| 013 | Implement DevStatusCommand | 009 | pending |
| 014 | Add config/dev.php and wire configuration | 011 | pending |
| 015 | Create README.md for marko/dev-server | 014 | pending |

## Architecture Notes
- Command aliases are a core feature added to `#[Command]` attribute via an `aliases` parameter
- Aliases are stored in CommandDefinition and indexed separately in CommandRegistry
- Alias conflicts with existing command names throw CommandException (loud errors)
- dev-server package uses `config/dev.php` with `true | string | false` pattern for each service
- ProcessManager uses `proc_open` for foreground mode, background processes for `--detach`
- PID file at `.marko/dev.json` stores process metadata for `dev:down` and `dev:status`
- Package manager detection: check lockfile presence (bun.lockb, pnpm-lock.yaml, yarn.lock, package-lock.json)

## Risks & Mitigations
- **pcntl extension not available**: Check for `pcntl_signal` availability, degrade gracefully with warning
- **Port already in use**: Detect and show helpful error with port suggestion
- **Docker not installed**: Detect missing binary, skip Docker with informational message
- **Process cleanup on crash**: PID file allows `dev:down` to clean up orphaned processes
