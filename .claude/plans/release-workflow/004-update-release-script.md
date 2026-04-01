# Task 004: Update Release Script

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Update `bin/release.sh` to automatically create a GitHub Release with auto-generated notes after pushing the tag. This makes the release script the single command needed for a complete release.

## Context
- Related files: `bin/release.sh` (existing — modify)
- Current flow: validate → merge develop→main → test → tag → push → checkout develop
- New addition: after pushing the tag, create a GitHub Release via `gh release create`
- Uses `--generate-notes` to pull categorized PR summaries from `.github/release.yml`
- Uses `--latest` to mark it as the latest release
- Should find the previous tag automatically for proper note range

## Requirements (Test Descriptions)
- [x] `it creates a GitHub Release after pushing the tag`
- [x] `it uses --generate-notes for automatic release note generation`
- [x] `it marks the release as latest with --latest flag`
- [x] `it references the previous tag for note range using --notes-start-tag when a previous tag exists`
- [x] `it omits --notes-start-tag when no previous tag exists (first release)`
- [x] `it does not fail the entire release if gh release create fails`
- [x] `it validates that gh CLI is available before attempting release creation`
- [x] `it pushes develop branch to origin after merging main back into develop`
- [x] `it updates the Next steps output to mention the GitHub Release`

## Acceptance Criteria
- Existing validation, testing, and merge logic unchanged
- GitHub Release creation is inserted between `git push origin "$TAG"` (line 67) and `git checkout develop` (line 69) in the existing script
- The `gh` availability check should go near the existing PHP version check section (around lines 35-44)
- Failure to create release prints a warning but does not exit the script
- **IMPORTANT**: The script uses `set -euo pipefail`. The release creation block must be wrapped to prevent `set -e` from aborting on failure. Use a subshell or explicit `if` block — not just `|| true` — for both the previous-tag lookup and the `gh release create` call
- Previous tag is determined dynamically using `git describe --tags --abbrev=0 HEAD^` or similar, but MUST handle the case where no previous tag exists (first release) by omitting `--notes-start-tag`
- If `gh` is not installed, print a warning that the GitHub Release must be created manually, but do NOT fail the release

## Implementation Notes
- Added `GH_AVAILABLE` flag (lines 46-52) near the PHP check using `command -v gh`; missing `gh` prints a warning but does not fail
- GitHub Release block (lines 77-106) inserted between `git push origin "$TAG"` and `git checkout develop`
- Previous tag lookup uses `if PREV_TAG_CANDIDATE=$(git describe --tags --abbrev=0 HEAD^ 2>/dev/null)` — safe under `set -e` because the command is an `if` condition, not a bare statement
- Both `gh release create` call sites are also `if` conditions, preventing `set -e` from aborting on failure; a warning is printed instead
- `git push origin develop` added on line 111 after `git merge main`
- "Next steps" output updated to list GitHub Release as item 1, existing items renumbered 2-4
