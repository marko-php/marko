# Task 011: ProjectFinder

**Status**: pending
**Depends on**: 010
**Retry count**: 0

## Description
Create `ProjectFinder` class that locates the Marko project root by traversing up the directory tree looking for `vendor/marko/core`. This enables running `marko` from any subdirectory.

## Context
- Directory: `packages/cli/src/ProjectFinder.php`
- Pattern: Traverse up from current directory until finding marker
- Marker: `vendor/marko/core` directory existence

## Requirements (Test Descriptions)
- [ ] `it finds project root when in project directory`
- [ ] `it finds project root when in subdirectory of project`
- [ ] `it returns null when not in a Marko project`
- [ ] `it stops at filesystem root without infinite loop`
- [ ] `it returns absolute path to project root`
- [ ] `it detects project by presence of vendor/marko/core`
- [ ] `it handles symlinked directories correctly`

## Acceptance Criteria
- All requirements have passing tests
- Works on all platforms (Unix, Windows paths)
- No external dependencies
- Safe against infinite loops
- Code follows code standards
