# Task 001: Add short option parsing to Input

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Enhance the `Input` class to recognize single-character short options (`-d`, `-p=8000`, `-p 8000`) alongside existing long options (`--detach`, `--port=8000`). This enables CLI shortcuts like `marko up -d` instead of `marko up --detach`.

## Context
- Related files: `packages/core/src/Command/Input.php`, `packages/core/tests/Unit/Command/InputOutputTest.php`
- The Input class currently only parses `--name` and `--name=value` patterns
- Short options are single-character: `-d` (flag), `-p=8000` (equals), `-p 8000` (next-arg value)
- `hasOption('d')` should match `-d` in argv
- `getOption('p')` should return `'8000'` for both `-p=8000` and `-p 8000`
- Input is a `readonly class` — no new properties needed, just enhanced parsing logic
- For `-x value` (space-separated), check the next argument in the array if it doesn't start with `-`

## Requirements (Test Descriptions)
- [ ] `it recognizes short flag option`
- [ ] `it returns true value for short flag option`
- [ ] `it returns short option value with equals syntax`
- [ ] `it returns short option value from next argument`
- [ ] `it returns null for missing short option`
- [ ] `it does not treat short option as long option`

## Acceptance Criteria
- All requirements have passing tests
- Existing Input tests still pass unchanged
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
