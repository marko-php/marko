# Task 003: Input and Output Classes

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create `Input` and `Output` classes for command I/O. Input parses command-line arguments, Output handles writing to console. Keep these simple - no advanced terminal features yet.

## Context
- Directory: `packages/core/src/Command/Input.php`, `packages/core/src/Command/Output.php`
- Pattern: Simple value objects for command I/O
- Focus: Basic argument access and console writing

## Requirements (Test Descriptions)
- [ ] `it creates Input from array of arguments`
- [ ] `it returns command name as first argument`
- [ ] `it returns remaining arguments after command name`
- [ ] `it checks if argument exists by index`
- [ ] `it returns null for missing argument`
- [ ] `it creates Output that writes to stream`
- [ ] `it writes line with newline character`
- [ ] `it writes text without newline character`
- [ ] `it writes empty line`
- [ ] `it defaults Output to STDOUT`

## Acceptance Criteria
- All requirements have passing tests
- Input handles argv-style arrays
- Output is testable (accepts stream parameter)
- No external dependencies
- Code follows code standards
