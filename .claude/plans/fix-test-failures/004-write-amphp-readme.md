# Task 004: Write amphp README

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
The amphp package README is missing sections that the PackageScaffoldingTest expects. Add Overview, Installation, Usage, and API Reference sections based on actual package code.

## Context
- Related files:
  - `packages/amphp/README.md` — current README (has title, installation, quick example, docs link)
  - `packages/amphp/tests/PackageScaffoldingTest.php` — test expectations
  - `packages/amphp/src/` — source code to base documentation on
  - Has docs page at `docs/src/content/docs/packages/amphp.md`
- Test expects these sections exist: `## Overview`, `## Installation`, `## Usage`, `## API Reference`
- Read the source code to write accurate content

## Requirements (Test Descriptions)
- [ ] `it creates README.md for marko/amphp with all required sections` — README contains Overview, Installation, Usage, and API Reference sections

## Acceptance Criteria
- All requirements have passing tests
- README content accurately reflects actual package code
- Content is based on reading source files, not fabricated
