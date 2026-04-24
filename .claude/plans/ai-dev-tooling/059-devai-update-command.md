# Task 059: Implement devai:update command

**Status**: pending
**Depends on**: 058
**Retry count**: 0

## Description
Implement `marko devai:update` — regenerates all per-agent configs from current Marko guidelines + aggregated package contributions + skill updates. Used after Marko upgrades or new contributor packages installed.

## Context
- Namespace: `Marko\DevAi\Commands\UpdateCommand`
- Attribute: `#[Command(name: 'devai:update')]`
- Reads the prior selection (which agents + which docs driver) from `.marko/devai.json` written by install
- If no prior selection found, errors with suggestion to run `devai:install` first

## Requirements (Test Descriptions)
- [ ] `it is registered via Command attribute with name devai:update`
- [ ] `it reads prior agent selection from .marko/devai.json`
- [ ] `it re-runs each previously selected adapter`
- [ ] `it detects and reports newly contributed guidelines from new packages`
- [ ] `it prints a summary of files changed`
- [ ] `it errors with helpful suggestion when no prior install config exists`

## Acceptance Criteria
- Safe to re-run repeatedly
- Picks up package changes since last run

## Implementation Notes
(Filled in by programmer during implementation)
