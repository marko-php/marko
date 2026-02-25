# Task 013: Implement DevStatusCommand

**Status**: completed
**Depends on**: 009
**Retry count**: 0

## Description
Create the `DevStatusCommand` that displays the current state of development services. Reads the PID file and checks if each recorded process is still alive, displaying a formatted table with process name, PID, status, port, and uptime.

## Context
- Related files: `packages/dev-server/src/Command/DevStatusCommand.php`
- Command: `#[Command(name: 'dev:status', description: 'Show development environment status')]`
- No alias for this command (it's less frequently used)
- Reads `.marko/dev.json` for process list
- Checks each PID for liveness
- Output format: table with Name, PID, Status (running/stopped), Port, Uptime columns
- If no PID file exists, output "No development services running"

## Requirements (Test Descriptions)
- [ ] `it has Command attribute with name dev:status`
- [ ] `it reads process list from PID file`
- [ ] `it displays process name, PID, status, and port`
- [ ] `it shows running status for alive processes`
- [ ] `it shows stopped status for dead processes`
- [ ] `it outputs message when no services are running`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- Clear, readable output format

## Implementation Notes
(Left blank - filled in by programmer during implementation)
