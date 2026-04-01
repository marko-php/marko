# Task 006: PR Template

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a pull request template that appears when opening new PRs. Includes a summary section, type of change checkboxes, and a submission checklist.

## Context
- Related files: `.github/PULL_REQUEST_TEMPLATE.md` (new)
- Keep it minimal — developers shouldn't feel burdened by the template
- Type checkboxes help reviewers quickly understand the PR scope
- Checklist ensures basic quality gates are met before review

## Requirements (Test Descriptions)
- [x] `it includes a Summary section`
- [x] `it includes type of change checkboxes for bug fix, new feature, breaking change, docs, and refactor`
- [x] `it includes a checklist for tests passing, lint passing, and code standards`
- [x] `it includes a line for referencing related issues`

## Acceptance Criteria
- Markdown format
- Concise — fits on one screen
- Checkboxes use `- [ ]` format
- No verbose instructions or boilerplate

## Implementation Notes
Created `.github/PULL_REQUEST_TEMPLATE.md` with Summary section, Type of Change checkboxes (bug fix, new feature, breaking change, docs, refactor), Related Issues line using `Closes #`, and Checklist for tests, lint, and code standards. File is 21 lines — fits on one screen.
