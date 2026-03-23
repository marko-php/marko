# Task 007: Remove Demo Directory and Clean Up References

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Remove the entire `demo/` directory. It was scaffolding for early integration testing and is no longer needed — the 70 packages have comprehensive test suites, and real applications will serve as integration tests. Also clean up any references to demo/ in documentation or configuration files.

## Context
- `demo/` contains: `composer.json`, `app/blog/`, `public/index.php`, `modules/`, `vendor/`
- CLAUDE.md references demo/ extensively — these sections need updating
- The demo was explicitly described as "minimal bootstrap infrastructure for testing that packages integrate correctly"
- With proper Packagist publishing, users create real projects instead

## Requirements (Test Descriptions)
- [x] `it removes the entire demo/ directory`
- [x] `it updates CLAUDE.md to remove demo/ references and the demo application section`
- [x] `it removes any other references to demo/ in .claude/ configuration files`

## Acceptance Criteria
- `demo/` directory no longer exists
- CLAUDE.md no longer references demo/
- No broken references to demo/ anywhere in `.claude/` docs
- No references to demo/ in root composer.json

## Implementation Notes
- Delete `demo/` recursively
- In CLAUDE.md: remove the "Demo Application" section and the "What belongs in demo" / "What does NOT belong in demo" sections, including the critical warning block about demo/app/ customization
- Check `.claude/architecture.md`, `.claude/project-overview.md`, and `.claude/testing.md` for demo references
