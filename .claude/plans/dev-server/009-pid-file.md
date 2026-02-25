# Task 009: Implement PidFile

**Status**: completed
**Depends on**: 006
**Retry count**: 0

## Description
Create `PidFile` class for reading and writing process state to `.marko/dev.json`. This file tracks running processes started by `dev:up --detach` so that `dev:down` and `dev:status` can manage them. Stores process name, PID, command, port, and start time.

## Context
- Related files: `packages/dev-server/src/Process/PidFile.php`
- File location: `{projectRoot}/.marko/dev.json`
- Creates `.marko/` directory if it doesn't exist
- JSON structure: `{ "processes": [ { "name": "php", "pid": 12345, "command": "php -S ...", "port": 8000, "startedAt": "2026-02-25T10:00:00+00:00" } ] }`
- Should handle missing file gracefully (return empty state)
- Should handle corrupt JSON gracefully (treat as empty, warn)
- Provides methods to check if a PID is still running (via `posix_kill($pid, 0)` or `/proc` check)

## Requirements (Test Descriptions)
- [ ] `it writes process entries to JSON file`
- [ ] `it reads process entries from JSON file`
- [ ] `it creates .marko directory if it does not exist`
- [ ] `it returns empty array when file does not exist`
- [ ] `it removes the PID file via clear method`
- [ ] `it checks if a process is still running`
- [ ] `it stores process name, pid, command, port, and start time`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- Uses temp directories for testing

## Implementation Notes
(Left blank - filled in by programmer during implementation)
