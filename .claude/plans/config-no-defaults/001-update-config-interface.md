# Task 001: Update ConfigRepositoryInterface

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Remove default parameters from all getter methods in ConfigRepositoryInterface. Methods should throw ConfigNotFoundException when keys are missing.

## Context
- Related files: `packages/config/src/ConfigRepositoryInterface.php`
- This is the core interface change that all other tasks depend on

## Requirements (Test Descriptions)
- [ ] `it defines get method without default parameter`
- [ ] `it defines getString method without default parameter`
- [ ] `it defines getInt method without default parameter`
- [ ] `it defines getBool method without default parameter`
- [ ] `it defines getFloat method without default parameter`
- [ ] `it defines getArray method without default parameter`

## Acceptance Criteria
- All requirements have passing tests
- Interface methods have no default parameters except scope
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
