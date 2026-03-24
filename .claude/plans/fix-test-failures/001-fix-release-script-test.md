# Task 001: Fix Release Script Test

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
The release script was refactored to auto-checkout main and merge develop, removing the previous branch validation. The test still expects the old validation behavior. Update the test to match the current script behavior.

## Context
- Related files: `tests/ReleaseScriptTest.php`, `bin/release.sh`
- The test "validates the current branch is main" at lines 54-60 currently asserts:
  ```php
  expect($contents)->toContain('main')
      ->and($contents)->toContain('git branch --show-current')
      ->and($contents)->toContain("Must be on 'main' branch");
  ```
- The script was refactored: it no longer validates the branch. Instead it auto-checkouts main and merges develop (lines 22-26):
  ```bash
  git checkout main
  git pull origin main
  git merge develop
  ```
- The test must be updated to verify the auto-checkout behavior instead of the removed validation

## Requirements (Test Descriptions)
- [ ] `it validates the current branch is main` — update assertions to check that `bin/release.sh` contains `git checkout main` and `git merge develop` instead of `git branch --show-current` and `Must be on 'main' branch`
- [ ] All other release script tests continue to pass unchanged (do NOT modify any other test in the file)

## Acceptance Criteria
- All requirements have passing tests
- The test accurately reflects what `bin/release.sh` actually does
- No other tests regress
