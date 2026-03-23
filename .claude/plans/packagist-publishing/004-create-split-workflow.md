# Task 004: Create GitHub Actions Split Workflow

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create a GitHub Actions workflow that uses splitsh-lite to split each of the 70 package subdirectories into their own read-only repositories under the `marko-php` org. This runs on tag push (for releases) and branch push (for dev access). This is the same approach Symfony and Laravel use for monorepo package distribution.

## Context
- Workflow location: `.github/workflows/split.yml`
- Existing workflow: `.github/workflows/deploy-docs.yml` (don't modify)
- Split repos: `marko-php/marko-{package-name}`
- The workflow dynamically generates the package list from `packages/` directory
- Requires `SPLIT_TOKEN` secret (GitHub PAT with repo access to split repos)
- splitsh-lite is available as a standalone binary and as GitHub Actions

## Requirements (Test Descriptions)
- [x] `it creates a split workflow at .github/workflows/split.yml`
- [x] `it triggers on tag push matching v* pattern`
- [x] `it triggers on push to main and develop branches`
- [x] `it dynamically discovers packages from the packages directory`
- [x] `it splits each package subdirectory to its own repository`
- [x] `it tags each split repository with the same version tag on tag push`
- [x] `it pushes branch updates to split repos on branch push`
- [x] `it uses SPLIT_TOKEN secret for authentication`
- [x] `it configures the target organization as an environment variable for easy changes`

## Acceptance Criteria
- Workflow file is valid YAML and valid GitHub Actions syntax
- Workflow handles both tag and branch push events correctly
- Package discovery is automatic (no hardcoded list that goes stale)
- Split repo naming follows `marko-{package}` convention
- The org name is a single variable at the top, easy to change
- Workflow is efficient (parallel splits where possible)
- Has concurrency group to prevent parallel split workflow runs from conflicting

## Implementation Notes

### splitsh-lite approach
Use the `splitsh/lite` binary directly. The workflow should:

1. **Checkout** the full repo with complete history (`fetch-depth: 0`)
2. **Discover packages**: `ls packages/` to build the split matrix
3. **For each package**: run `splitsh-lite --prefix=packages/{name}` to get the split SHA
4. **Push** the split SHA to `marko-php/marko-{name}` (branch or tag depending on trigger)

### Workflow structure
```yaml
name: Split Packages

on:
  push:
    branches: [main, develop]
    tags: ['v*']

concurrency:
  group: split-${{ github.ref }}
  cancel-in-progress: false

env:
  SPLIT_ORG: marko-php  # Change this to your GitHub org

jobs:
  setup:
    # Output package list as JSON
  split:
    needs: setup
    strategy:
      matrix:
        package: ${{ fromJson(needs.setup.outputs.packages) }}
      fail-fast: false
    steps:
      - checkout with full history
      - install splitsh-lite
      - split and push
```

### Key considerations
- Use a matrix strategy with `fromJson` to dynamically generate the package list
- Need a setup job that outputs the package list, then a split job that uses it
- For tags: push both the branch and the tag to the split repo
- The `SPLIT_TOKEN` needs `repo` scope to push to the split repos
- The first push to a newly created (empty) split repo requires force push since there's no existing history — use `git push --force` for the split pushes
- Add a concurrency group to prevent parallel split workflow runs from conflicting (e.g., tag push + branch push at the same time)
