# Task 006: Create Release Script

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create a release script that validates preconditions and tags the monorepo. Once the tag is pushed, the split workflow (Task 004) handles everything else automatically. The script enforces a strict, repeatable process so the user never needs to think about release mechanics.

## Context
- Script location: `bin/release.sh`
- The split workflow triggers on tag push — this script just needs to validate and tag
- Version format: semver (e.g., `0.1.0`, `1.0.0`)
- Tags use `v` prefix (e.g., `v0.1.0`)
- Must be on `main` branch to release
- Working directory must be clean
- Tests must pass before tagging

## Requirements (Test Descriptions)
- [x] `it creates bin/release.sh with version argument validation`
- [x] `it validates PHP version is 8.5+ (checks /opt/homebrew/Cellar/php/8.5.1_2/bin/php or system php)`
- [x] `it validates semver format (rejects invalid versions like 1.0 or v1.0.0)`
- [x] `it validates the current branch is main`
- [x] `it validates the working directory is clean (no uncommitted changes)`
- [x] `it validates the tag does not already exist`
- [x] `it runs the test suite and aborts if tests fail`
- [x] `it creates an annotated git tag with v prefix`
- [x] `it pushes the tag to origin`
- [x] `it prints a summary of what was released and next steps`

## Acceptance Criteria
- Script accepts version as argument: `./bin/release.sh 0.1.0`
- Script refuses to run from any branch other than `main`
- Script refuses to run with uncommitted changes
- Script refuses to create duplicate tags
- Tests must pass (using project's test command) before tag is created
- Tag is annotated (not lightweight) with a release message
- Script outputs clear success message with Packagist link

## Implementation Notes
```bash
#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:?Usage: ./bin/release.sh <version> (e.g., 0.1.0)}"
TAG="v${VERSION}"

# Validate semver format
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Invalid semver: $VERSION"; exit 1; }

# Validate branch
[[ "$(git branch --show-current)" == "main" ]] || { echo "Must be on main branch"; exit 1; }

# Validate clean working directory
git diff --quiet && git diff --cached --quiet || { echo "Working directory not clean"; exit 1; }

# Validate tag doesn't exist
! git rev-parse "$TAG" >/dev/null 2>&1 || { echo "Tag $TAG already exists"; exit 1; }

# Find PHP 8.5+ binary
PHP_BIN="/opt/homebrew/Cellar/php/8.5.1_2/bin/php"
[[ -x "$PHP_BIN" ]] || PHP_BIN="php"
PHP_VERSION=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
[[ "$PHP_VERSION" == "8.5" ]] || { echo "PHP 8.5+ required, found $PHP_VERSION"; exit 1; }

# Run tests
"$PHP_BIN" vendor/bin/pest --parallel || { echo "Tests failed — aborting release"; exit 1; }

# Create and push tag
git tag -a "$TAG" -m "Release $VERSION"
git push origin "$TAG"

echo "Released $VERSION — split workflow will publish packages to Packagist"
```

Note: Use the project's PHP binary path for tests: `/opt/homebrew/Cellar/php/8.5.1_2/bin/php`
