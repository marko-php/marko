# Task 060: Update skeleton installer to prompt for devai

**Status**: pending
**Depends on**: 058
**Retry count**: 0

## Description
Update the `marko/skeleton` package's post-create hook/installer to ask: "Install marko/devai for AI-assisted development? (recommended)". **Checked by default** — nearly every developer will want the recommended AI-assisted setup, and an unchecked default would suppress adoption. Users can easily uncheck if they want the bare skeleton. If accepted, adds `marko/devai` to composer require-dev.

## Context
- File: `packages/skeleton/` installer script or `composer.json` scripts
- Prompts are displayed after composer create-project completes
- If user opts in, `composer require --dev marko/devai` is run

## Requirements (Test Descriptions)
- [ ] `it displays the devai opt-in prompt after project creation`
- [ ] `it defaults to checked (install recommended)`
- [ ] `it runs composer require --dev marko/devai when user accepts`
- [ ] `it skips cleanly when user declines`
- [ ] `it records the choice so later skeleton updates don't re-prompt`

## Acceptance Criteria
- Prompt only shown once per project
- Non-interactive installs default to installing (follows the checked default; override with explicit --no-devai flag)

## Implementation Notes
(Filled in by programmer during implementation)
