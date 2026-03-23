# Task 008: Update project-overview.md

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Update the project overview document to reflect the correct GitHub organization, repository structure, and remove demo references.

## Context
- File: `.claude/project-overview.md`
- Currently references `github.com/devtomic/marko` and organization `github.com/devtomic`
- Monorepo will be transferred to: `github.com/marko-php/marko`
- Split repos: `github.com/marko-php/marko-{package}`
- Organization: `marko-php` (owned by Devtomic LLC)
- Demo directory is being removed (Task 007)

## Requirements (Test Descriptions)
- [ ] `it updates the repository URL from devtomic/marko to marko-php/marko`
- [ ] `it updates the organization reference to marko-php`
- [ ] `it removes demo/ from the key directories section`
- [ ] `it preserves all other content in project-overview.md`

## Acceptance Criteria
- Repository URL matches `marko-php/marko`
- Organization reference is `marko-php`
- No references to demo/ directory
- All other content preserved
