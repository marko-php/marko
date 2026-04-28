# Task 061: Implement skeleton post-create hook to trigger devai:install

**Status**: pending
**Depends on**: 060
**Retry count**: 0

## Description
When the user accepts devai in the skeleton prompt (task 060), automatically trigger `marko devai:install` as part of the post-create flow so the user lands in a fully-configured project.

## Context
- Invoked after `composer require --dev marko/devai` succeeds
- Invokes devai:install in interactive mode unless skeleton was itself run non-interactively

## Requirements (Test Descriptions)
- [ ] `it runs marko devai:install after devai is added`
- [ ] `it respects non-interactive mode by defaulting to sensible agent auto-detection and vec docs driver`
- [ ] `it aborts cleanly if devai:install fails without rolling back composer require`
- [ ] `it prints a clear next-step message if the user skipped devai`

## Acceptance Criteria
- End-to-end smoke test: create-project → prompt accepted → devai:install runs → project has CLAUDE.md + AGENTS.md

## Implementation Notes
(Filled in by programmer during implementation)
