# Task 003: Auto-Labeling Workflow

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create a GitHub Actions workflow that automatically applies labels to PRs based on the conventional commit prefix in the PR title. This eliminates manual labeling and ensures release notes are always properly categorized.

## Context
- Related files: `.github/workflows/auto-label.yml` (new)
- **MUST use `pull_request_target`** (not `pull_request`) to have write access on fork PRs. This is safe because the workflow only reads the PR title/body metadata, never checks out or executes PR code
- Triggers on `pull_request_target` open and title edit (types: opened, edited)
- **MUST declare `permissions: pull-requests: write`** in the workflow YAML so the GITHUB_TOKEN can modify labels
- Uses a lightweight shell step with `gh pr edit --add-label` — no third-party actions
- Scope in prefix (e.g., `feat(auth):`) is ignored — only the type matters
- The `!` suffix (e.g., `feat!:`) or "BREAKING CHANGE" in PR body adds the `breaking` label

## Requirements (Test Descriptions)
- [x] `it triggers on pull_request_target opened and edited events`
- [x] `it labels fix: prefix PRs with bug`
- [x] `it labels fix(scope): prefix PRs with bug`
- [x] `it labels feat: prefix PRs with enhancement`
- [x] `it labels docs: prefix PRs with documentation`
- [x] `it labels refactor: prefix PRs with refactor`
- [x] `it labels test: prefix PRs with testing`
- [x] `it labels ci: prefix PRs with ci`
- [x] `it labels chore: prefix PRs with maintenance`
- [x] `it adds breaking label when type has ! suffix`
- [x] `it adds breaking label when PR body contains BREAKING CHANGE`
- [x] `it removes stale type labels when PR title changes (mutually exclusive set: bug, enhancement, documentation, refactor, testing, ci, maintenance — remove all before adding new one)`

## Acceptance Criteria
- Workflow is valid GitHub Actions YAML
- Uses `pull_request_target` event (NOT `pull_request`) for fork PR write access
- Declares explicit `permissions: pull-requests: write` at workflow or job level
- Uses `GITHUB_TOKEN` permissions (no extra secrets needed)
- Pattern matching is case-insensitive for the type prefix
- Handles edge cases: no prefix match (no label added, no error)

## Implementation Notes
- Created `.github/workflows/auto-label.yml` as a single-job workflow
- Uses `pull_request_target` with `types: [opened, edited]` for fork PR write access
- Declares `permissions: pull-requests: write` at the workflow level
- No third-party actions — pure shell using `gh pr edit`
- Type extraction uses a Perl-compatible regex `'^[a-z]+(?=(\([^)]*\))?!?:)'` that handles plain (`fix:`), scoped (`fix(scope):`), breaking (`fix!:`), and scoped breaking (`fix(scope)!:`) prefixes
- Pattern matching lowercases the captured type via `tr`, making it case-insensitive
- All mutually exclusive type labels are removed before applying the new one to handle PR title edits
- Breaking change detection: checks for `!` before `:` in title, or `BREAKING CHANGE` (case-insensitive) anywhere in the PR body
- `--remove-label` uses `2>/dev/null || true` so it silently ignores labels that aren't currently applied
- No extra secrets needed — uses `GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}`
