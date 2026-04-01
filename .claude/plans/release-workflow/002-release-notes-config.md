# Task 002: Release Notes Configuration

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create `.github/release.yml` that configures GitHub's auto-generated release notes to categorize PRs by label into meaningful sections.

## Context
- Related files: `.github/release.yml` (new)
- GitHub docs: release.yml maps labels to changelog categories
- Labels available after task 001: bug, enhancement, documentation, refactor, testing, ci, maintenance, breaking
- NOTE: This task can be built independently (the YAML file references label names as strings), but the categories only work at runtime once task 001 has been executed to create the labels
- Exclude noise labels: duplicate, invalid, wontfix, question, good first issue, help wanted

## Requirements (Test Descriptions)
- [x] `it creates a valid release.yml in .github directory`
- [x] `it maps breaking label to Breaking Changes category`
- [x] `it maps enhancement label to New Features category`
- [x] `it maps bug label to Bug Fixes category`
- [x] `it maps documentation label to Documentation category`
- [x] `it maps refactor, testing, ci, and maintenance to their own categories`
- [x] `it excludes PRs with duplicate, invalid, wontfix, and question labels`

## Acceptance Criteria
- Valid YAML format
- Categories ordered by importance (breaking first, maintenance last)
- Excluded labels prevent noise in release notes

## Implementation Notes
Created `.github/release.yml` with the GitHub auto-generated release notes configuration.

- Categories ordered by importance: Breaking Changes, New Features, Bug Fixes, Documentation, Refactoring, Testing, CI, Maintenance
- Excluded labels: duplicate, invalid, wontfix, question, good first issue, help wanted
- Also excludes `good first issue` and `help wanted` per the task context (noise labels)
- The `exclude` block uses the top-level `changelog.exclude.labels` key per GitHub docs
- Labels referenced as strings — categories become active at runtime once task 001 creates the labels
