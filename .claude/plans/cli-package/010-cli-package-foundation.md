# Task 010: CLI Package Foundation

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the marko/cli package directory structure with composer.json. This establishes the thin client package that will be installed globally.

## Context
- Directory: `packages/cli/`
- Pattern: Follow existing package structure (routing, core)
- Key: This is a standalone package with minimal dependencies

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with name marko/cli`
- [ ] `it has composer.json with description for CLI tool`
- [ ] `it requires php ^8.5 in composer.json`
- [ ] `it has bin entry pointing to bin/marko in composer.json`
- [ ] `it has PSR-4 autoload for Marko\\Cli namespace`
- [ ] `it has src directory for source files`
- [ ] `it has bin directory for executable`
- [ ] `it has tests directory structure`

## Acceptance Criteria
- All requirements have passing tests
- composer.json is valid and installable
- No dependency on marko/core (thin client principle)
- Directory structure matches existing packages
- Code follows code standards
