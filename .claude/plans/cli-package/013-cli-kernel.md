# Task 013: CliKernel

**Status**: pending
**Depends on**: 011, 012
**Retry count**: 0

## Description
Create `CliKernel` class that orchestrates CLI execution: finds project, boots Application, and delegates to command runner. This is the main entry point logic for the CLI.

## Context
- Directory: `packages/cli/src/CliKernel.php`
- Dependencies: ProjectFinder, uses project's Application
- Pattern: Thin orchestration layer

## Requirements (Test Descriptions)
- [ ] `it finds project root using ProjectFinder`
- [ ] `it throws ProjectNotFoundException when not in project`
- [ ] `it loads project autoloader from vendor/autoload.php`
- [ ] `it boots project Application`
- [ ] `it delegates command execution to Application commandRunner`
- [ ] `it returns exit code from command execution`
- [ ] `it shows list command output when no command specified`
- [ ] `it catches and displays exceptions with helpful messages`

## Acceptance Criteria
- All requirements have passing tests
- Clean separation: find project → boot → run
- Proper exception handling with user-friendly output
- No hardcoded paths
- Code follows code standards
