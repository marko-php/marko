# Task 009: Write pubsub READMEs (pubsub, pubsub-pgsql, pubsub-redis)

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Three pubsub-related READMEs need Overview, Installation, Usage, and API Reference sections added. All follow the same pattern — the tests check for the same 4 section headers.

## Context
- Related files:
  - `packages/pubsub/README.md` — current README (has title, installation, quick example, docs link)
  - `packages/pubsub-pgsql/README.md` — current README (has title, installation, quick example, docs link)
  - `packages/pubsub-redis/README.md` — current README (has title, installation, quick example, docs link)
  - `packages/pubsub/tests/PackageScaffoldingTest.php` — test for pubsub
  - `packages/pubsub-pgsql/tests/PackageScaffoldingTest.php` — test for pubsub-pgsql
  - `packages/pubsub-redis/tests/PackageScaffoldingTest.php` — test for pubsub-redis
  - Source directories for all 3 packages
  - All have docs pages
- All 3 tests check for: `## Overview`, `## Installation`, `## Usage`, `## API Reference`
- Read source code for each package to write accurate content

## Requirements (Test Descriptions)
- [ ] `it creates README.md for marko/pubsub with all required sections` — Overview, Installation, Usage, API Reference
- [ ] `it creates README.md for marko/pubsub-pgsql with all required sections` — Overview, Installation, Usage, API Reference
- [ ] `it creates README.md for marko/pubsub-redis with all required sections` — Overview, Installation, Usage, API Reference

## Acceptance Criteria
- All 3 requirements have passing tests
- README content accurately reflects actual pubsub package interfaces and classes
- Existing passing tests continue to pass
