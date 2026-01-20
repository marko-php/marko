# Task 014: bin/marko Executable

**Status**: pending
**Depends on**: 013
**Retry count**: 0

## Description
Create the `bin/marko` executable script that serves as the global entry point. This is a minimal PHP script that instantiates CliKernel and runs it with argv.

## Context
- Directory: `packages/cli/bin/marko`
- Pattern: Minimal executable, delegates to CliKernel
- Note: Must be executable on Unix (shebang line)

## Requirements (Test Descriptions)
- [ ] `it has PHP shebang line for direct execution`
- [ ] `it requires own autoloader for Marko\\Cli classes`
- [ ] `it instantiates CliKernel`
- [ ] `it passes argv to kernel run method`
- [ ] `it exits with code returned from kernel`
- [ ] `it handles uncaught exceptions gracefully`
- [ ] `it displays error message on failure`
- [ ] `it returns exit code 1 on unhandled error`

## Acceptance Criteria
- All requirements have passing tests
- Executable is minimal (< 20 lines)
- Works when installed globally via Composer
- Proper error handling for edge cases
- Code follows code standards
