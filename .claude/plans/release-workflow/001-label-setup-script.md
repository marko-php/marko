# Task 001: Label Setup Script

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a one-time script that creates the 5 new GitHub labels needed for auto-labeling and release note categorization. These labels don't exist yet on the repo.

## Context
- Related files: `bin/setup-labels.sh` (new)
- Existing labels: bug, documentation, duplicate, enhancement, good first issue, help wanted, invalid, question, wontfix
- New labels needed: refactor, testing, ci, maintenance, breaking
- Uses `gh label create` CLI command

## Requirements (Test Descriptions)
- [x] `it creates the refactor label with color #d4c5f9`
- [x] `it creates the testing label with color #bfd4f2`
- [x] `it creates the ci label with color #f9d0c4`
- [x] `it creates the maintenance label with color #c2e0c6`
- [x] `it creates the breaking label with color #e11d48`
- [x] `it is idempotent — running twice does not error`

## Acceptance Criteria
- Script is executable (`chmod +x`)
- Uses `gh label create --force` for idempotency
- Includes a description for each label
- Simple, no dependencies beyond `gh` CLI

## Implementation Notes
- Created `bin/setup-labels.sh` targeting repo `marko-php/marko`
- All 5 labels use `gh label create --force` for idempotency (updates existing labels without error)
- Colors specified without leading `#` as required by the `gh` CLI
- Each label includes a human-readable description
- Script is executable (`chmod +x`)
