# Task 012: Implement DevDownCommand

**Status**: completed
**Depends on**: 009, 010
**Retry count**: 0

## Description
Create the `DevDownCommand` that stops all development processes started by `dev:up`. Reads the PID file to find running processes, sends SIGTERM to stop them, runs Docker Compose down if Docker was started, and cleans up the PID file.

## Context
- Related files: `packages/dev-server/src/Command/DevDownCommand.php`
- Command: `#[Command(name: 'dev:down', description: 'Stop the development environment', aliases: ['down'])]`
- Reads `.marko/dev.json` for process list
- For Docker processes: runs the corresponding `docker compose down` command
- For other processes: sends SIGTERM via `posix_kill`
- Cleans up PID file after stopping
- If no PID file exists, output "No development services running" message
- If a process is already dead, note it and continue cleanup

## Requirements (Test Descriptions)
- [ ] `it has Command attribute with name dev:down and alias down`
- [ ] `it reads process list from PID file`
- [ ] `it stops processes recorded in PID file`
- [ ] `it cleans up PID file after stopping`
- [ ] `it outputs message when no services are running`
- [ ] `it handles already-dead processes gracefully`
- [ ] `it runs docker compose down for Docker processes`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- Graceful handling of edge cases (missing file, dead processes)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
